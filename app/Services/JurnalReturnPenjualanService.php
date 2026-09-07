<?php

namespace App\Services;

use App\Models\BukuKitab;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\Penjualan;
use App\Models\ReturnPenjualan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class JurnalReturnPenjualanService
{
    /**
     * 8 Rekening / Akun Pengembalian yang tersedia untuk Retur.
     */
    public const AKUN_REFUND = [
        '1101.1' => [
            'nama' => 'KAS BU MUT',
            'suffix' => 'kas_bu_mut',
            'metode' => 'TUNAI',
        ],
        '1101.3' => [
            'nama' => 'BANK 99',
            'suffix' => 'bank_99',
            'metode' => 'TRANSFER',
        ],
        '1101.4' => [
            'nama' => 'BANK WAHANA',
            'suffix' => 'bank_wahana',
            'metode' => 'TRANSFER',
        ],
        '1101.5' => [
            'nama' => 'BANK WPI',
            'suffix' => 'bank_wpi',
            'metode' => 'TRANSFER',
        ],
        '1101.6' => [
            'nama' => 'BANK INDUSTRI',
            'suffix' => 'bank_industri',
            'metode' => 'TRANSFER',
        ],
        '1101.7' => [
            'nama' => 'BANK INTAN',
            'suffix' => 'bank_intan',
            'metode' => 'TRANSFER',
        ],
        '1101.8' => [
            'nama' => 'BANK BU EDDY',
            'suffix' => 'bank_bu_eddy',
            'metode' => 'TRANSFER',
        ],
        '2228.0' => [
            'nama' => 'LIABILITAS JK PENDEK LAINNYA',
            'suffix' => 'liabilitas_jk_pendek',
            'metode' => 'LIABILITAS',
        ],
    ];

    public function __construct(
        private readonly BukuKitabJurnalService $engine,
    ) {}

    /**
     * Tentukan kode Buku Kitab dari ke-16 Buku Kitab Retur yang tersedia (8 PPN & 8 Non-PPN).
     */
    public static function tentukanKodeKitab(
        bool $isPpn,
        ?string $akunPengembalian = '1101.1'
    ): string {
        $suffix = self::AKUN_REFUND[$akunPengembalian]['suffix'] ?? 'kas_bu_mut';

        return $isPpn
            ? "retur_normal_{$suffix}"
            : "retur_normal_non_ppn_{$suffix}";
    }

    /**
     * Hitung rincian nominal dan preview Buku Kitab untuk transaksi retur.
     *
     * @param  array<int, array{id_barang: int, nama_barang: string, qty: float, harga_jual: float, harga_beli: float, potongan: float}>  $itemsRetur
     */
    public function kalkulasi(
        Penjualan $penjualan,
        array $itemsRetur,
        ?string $akunPengembalian = '1101.1',
        ?int $excludeReturnId = null
    ): array {
        $penjualan->loadMissing(['details.barang']);

        $totalQtyBeliNota = (float) $penjualan->details->sum('qty');
        $totalQtyDiretur = (float) collect($itemsRetur)->sum('qty');

        // Cek retur sebelumnya dari nota ini (kecualikan retur saat ini jika sedang divalidasi/diedit)
        $qtyTereturSebelumnya = (float) DB::table('penjualan_return')
            ->join('penjualan_return_detail', 'penjualan_return.id', '=', 'penjualan_return_detail.id_return')
            ->where('penjualan_return.no_nota', $penjualan->no_nota)
            ->when($excludeReturnId, fn ($q) => $q->where('penjualan_return.id', '!=', $excludeReturnId))
            ->whereIn('penjualan_return.status_return', ['DIPROSES', 'DITERIMA', 'SELESAI'])
            ->sum('penjualan_return_detail.qty');

        $sisaQtyBisaDiretur = max(0, $totalQtyBeliNota - $qtyTereturSebelumnya);

        // Retur Penuh jika qty yang diretur sekarang menutup seluruh sisa yang belum diretur
        $isReturPenuh = ($totalQtyDiretur >= $sisaQtyBisaDiretur && $sisaQtyBisaDiretur > 0);

        $ppnPersenNota = (float) ($penjualan->ppn_persen ?? 0);
        $isPpn = ((float) $penjualan->ppn_nominal > 0 || $ppnPersenNota > 0);

        // Tentukan kategori jenis retur (Normal Penuh vs Sebagian)
        $jenisRetur = $isReturPenuh ? 'NORMAL' : 'SEBAGIAN';

        $kodeKitab = self::tentukanKodeKitab($isPpn, $akunPengembalian);
        $namaKitab = BukuKitab::where('kode', $kodeKitab)->value('nama') ?? $kodeKitab;

        // Hitung nominal rincian
        $subtotalRetur = 0.0;
        $totalHpp = 0.0;
        $breakdownPersediaan = [];
        $breakdownHpp = [];

        foreach ($itemsRetur as $item) {
            $qty = (float) ($item['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $hargaJual = (float) ($item['harga_jual'] ?? 0);
            $hargaBeli = (float) ($item['harga_beli'] ?? 0);
            $potongan = (float) ($item['potongan'] ?? 0);

            $nilaiBarang = ($qty * $hargaJual) - $potongan;
            $subtotalRetur += max(0, $nilaiBarang);

            $nilaiHppBarang = $qty * $hargaBeli;
            $totalHpp += $nilaiHppBarang;

            $namaBarang = $item['nama_barang'] ?? 'Barang';
            $idBarang = $item['id_barang'] ?? null;

            // Akun Persediaan Barang Jadi displit per barang (butuh id_barang agar
            // BukuKitabJurnalService bisa memisahkan baris jurnal per item, sesuai
            // 'splitHeaderPerBarang' => ['persediaan_barang_jadi'] di buatJurnalReturn()).
            $breakdownPersediaan[] = [
                'id_barang' => $idBarang,
                'nama_barang' => $namaBarang,
                'banyak' => $qty,
                'harga' => $hargaBeli,
                'keterangan' => "Retur Masuk Stok: {$namaBarang}",
            ];

            // Akun HPP TIDAK displit per barang, jadi sengaja TIDAK diberi 'id_barang'.
            // 'id_barang' hanya relevan untuk breakdown yang perlu dipisah per barang
            // (lihat splitHeaderPerBarang di buatJurnalReturn(), yang hanya
            // menyertakan 'persediaan_barang_jadi'). Kalau HPP ikut diberi id_barang,
            // ada risiko ikut displit per barang oleh engine jurnal padahal seharusnya
            // digabung jadi satu ringkasan.
            $breakdownHpp[] = [
                'nama_barang' => $namaBarang,
                'banyak' => $qty,
                'harga' => $hargaBeli,
                'keterangan' => "Retur HPP: {$namaBarang}",
            ];
        }

        // PPN proporsional
        $ppnNominal = 0.0;
        if ($isPpn) {
            if ($penjualan->sub_total > 0 && $penjualan->ppn_nominal > 0) {
                $proporsi = $subtotalRetur / (float) $penjualan->sub_total;
                $ppnNominal = round((float) $penjualan->ppn_nominal * $proporsi, 2);
            } elseif ($ppnPersenNota > 0) {
                $ppnNominal = round($subtotalRetur * ($ppnPersenNota / 100), 2);
            }
        }

        $totalNilaiRetur = $subtotalRetur + $ppnNominal;

        return [
            'is_dp' => ($penjualan->jenis_transaksi === 'DP'),
            'is_retur_penuh' => $isReturPenuh,
            'is_ppn' => $isPpn,
            'jenis_retur' => $jenisRetur,
            'kode_kitab' => $kodeKitab,
            'nama_kitab' => $namaKitab,
            'akun_pengembalian' => $akunPengembalian,
            'subtotal_retur' => $subtotalRetur,
            'ppn_nominal' => $ppnNominal,
            'total_retur' => $totalNilaiRetur,
            'total_hpp' => $totalHpp,
            'breakdown_persediaan' => $breakdownPersediaan,
            'breakdown_hpp' => $breakdownHpp,
        ];
    }

    /**
     * Buat jurnal pembantu otomatis untuk transaksi retur penjualan.
     */
    public function buatJurnalReturn(ReturnPenjualan $return, int $userId): void
    {
        $return->loadMissing(['details.barang', 'penjualan']);

        $penjualan = $return->penjualan ?: Penjualan::where('no_nota', $return->no_nota)->first();
        if (! $penjualan) {
            throw new RuntimeException("Data penjualan dengan no nota '{$return->no_nota}' tidak ditemukan.");
        }

        $noDokumen = $return->no_retur ?: "RET-{$return->id} ({$return->no_nota})";

        // Hindari duplikasi jika jurnal untuk no_dokumen ini sudah ada
        $existingHeader = JurnalPembantuHeader::where('no_dokumen', $noDokumen)
            ->where('modul_asal', 'penjualan_return')
            ->first();

        if ($existingHeader) {
            return;
        }

        $items = [];
        foreach ($return->details as $d) {
            $hargaBeli = (float) ($d->harga_beli ?: ($d->barang?->harga_beli ?? 0));
            $items[] = [
                'id_barang' => $d->id_barang,
                'nama_barang' => $d->nama_barang,
                'qty' => (float) $d->qty,
                'harga_jual' => (float) $d->harga_jual,
                'harga_beli' => $hargaBeli,
                'potongan' => (float) ($d->potongan ?? 0),
            ];
        }

        $calc = $this->kalkulasi(
            penjualan: $penjualan,
            itemsRetur: $items,
            akunPengembalian: $return->akun_pengembalian ?: '1101.1',
            excludeReturnId: $return->id
        );

        $kodeKitab = $return->kode_kitab ?: $calc['kode_kitab'];

        // Siapkan Context nilai untuk BukuKitabJurnalService
        // Semua retur (karena nota sudah lunas) diperlakukan sama menggunakan Buku Kitab retur normal
        $context = [
            'persediaan_barang_jadi' => $calc['total_hpp'],
            'hpp' => $calc['total_hpp'],
            'nilai_retur' => $calc['subtotal_retur'],
            'ppn_keluaran' => $calc['ppn_nominal'],
        ];

        $isLiabilitas = ($return->akun_pengembalian === '2228.0');

        if ($isLiabilitas) {
            $context['kewajiban_retur'] = $calc['total_retur'];
        } else {
            $context['nominal_kas'] = $calc['total_retur'];
        }

        $noDokumen = $return->no_retur ?: "RET-{$return->id} ({$return->no_nota})";

        DB::transaction(function () use (
            $kodeKitab,
            $context,
            $noDokumen,
            $return,
            $userId,
            $calc
        ) {
            $this->engine->buatJurnalDariKitab(
                kodeKitab: $kodeKitab,
                context: $context,
                noDokumen: $noDokumen,
                tglTransaksi: $return->tanggal ?: now(),
                modulAsal: 'penjualan_return',
                jenisTransaksi: 'bm',
                userId: $userId,
                jenisPihak: 'pelanggan',
                namaPihak: $return->nama_customer ?: 'Pelanggan',
                keteranganDefault: "Retur Penjualan | Ref: {$return->no_nota}",
                itemBreakdown: [
                    'persediaan_barang_jadi' => $calc['breakdown_persediaan'],
                    'hpp' => $calc['breakdown_hpp'],
                ],
                splitHeaderPerBarang: ['persediaan_barang_jadi'],
            );
        });
    }

    /**
     * Batalkan / Hapus jurnal retur yang masih berstatus draft jika validasi dibatalkan.
     */
    public function hapusJurnalReturn(ReturnPenjualan $return): void
    {
        $noDokumen = $return->no_retur ?: "RET-{$return->id} ({$return->no_nota})";

        $headers = JurnalPembantuHeader::where(function ($q) use ($noDokumen, $return) {
            $q->where('no_dokumen', $noDokumen)
                ->orWhere('no_dokumen', $return->no_nota);
        })
            ->where('modul_asal', 'penjualan_return')
            ->get();

        foreach ($headers as $h) {
            if ($h->status === JurnalPembantuHeader::STATUS_DRAFT) {
                JurnalPembantuItem::where('jurnal_pembantu_header_id', $h->id)->delete();
                $h->delete();
            }
        }
    }
}
