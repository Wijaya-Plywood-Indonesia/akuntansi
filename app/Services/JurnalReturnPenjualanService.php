<?php

namespace App\Services;

use App\Models\BukuKitab;
use App\Models\BukuKitabAkun;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\Penjualan;
use App\Models\ReturnPenjualan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class JurnalReturnPenjualanService
{
    /**
     * Fallback 8 Rekening / Akun Pengembalian jika database buku_kitabs belum tersedia.
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
     * Ambil daftar akun pengembalian secara dinamis langsung dari template Buku Kitab yang aktif.
     * Tidak lagi hardcode; rekening atau akun baru cukup ditambahkan ke Buku Kitab.
     *
     * @return array<string, array{nama: string, suffix: string, metode: string, variabel: string}>
     */
    public static function getAkunRefund(): array
    {
        $akuns = BukuKitabAkun::whereHas('bukuKitab', function ($q) {
                $q->where('is_active', true)->where('kode', 'like', 'retur_%');
            })
            ->where('posisi', 'k')
            ->whereIn('variabel_nilai', ['nominal_kas', 'kewajiban_retur'])
            ->select('no_akun', 'nama_akun', 'variabel_nilai')
            ->distinct()
            ->orderBy('no_akun')
            ->get();

        if ($akuns->isEmpty()) {
            return self::AKUN_REFUND;
        }

        $result = [];
        foreach ($akuns as $a) {
            $namaUpper = strtoupper($a->nama_akun);
            $metode = 'TRANSFER';
            if ($a->variabel_nilai === 'kewajiban_retur' || str_contains($namaUpper, 'LIABILITAS')) {
                $metode = 'LIABILITAS';
            } elseif (str_contains($namaUpper, 'KAS') || str_contains($namaUpper, 'TUNAI')) {
                $metode = 'TUNAI';
            }

            $suffix = Str::snake(Str::slug(preg_replace('/[^a-zA-Z0-9\s]/', '', $namaUpper), '_'));

            $result[$a->no_akun] = [
                'nama' => $namaUpper,
                'suffix' => $suffix,
                'metode' => $metode,
                'variabel' => $a->variabel_nilai,
            ];
        }

        return $result;
    }

    /**
     * Ambil info/konfigurasi akun pengembalian tertentu dari Buku Kitab.
     */
    public static function getAkunRefundConfig(?string $akun): array
    {
        $daftar = self::getAkunRefund();
        if ($akun && isset($daftar[$akun])) {
            return $daftar[$akun];
        }

        return [
            'nama' => 'KAS BU MUT',
            'suffix' => 'kas_bu_mut',
            'metode' => 'TUNAI',
            'variabel' => 'nominal_kas',
        ];
    }

    /**
     * Dapatkan akun pengembalian default dari Buku Kitab yang aktif.
     */
    public static function getDefaultAkunPengembalian(): string
    {
        $daftar = self::getAkunRefund();

        foreach ($daftar as $kode => $info) {
            if ($info['metode'] === 'TUNAI') {
                return (string) $kode;
            }
        }

        return (string) (array_key_first($daftar) ?? '1101.1');
    }

    /**
     * Cari model BukuKitab yang sesuai dengan status PPN dan akun pengembalian.
     * Sepenuhnya membaca relasi akun dan konfigurasi variabel di database.
     */
    public static function cariBukuKitab(
        bool $isPpn,
        ?string $akunPengembalian = null
    ): ?BukuKitab {
        $query = BukuKitab::query()
            ->where('is_active', true)
            ->where('kode', 'like', 'retur_%');

        // Filter berdasarkan ada tidaknya baris PPN di template Buku Kitab
        if ($isPpn) {
            $query->whereHas('akunDetail', function ($q) {
                $q->where('variabel_nilai', 'ppn_keluaran');
            });
        } else {
            $query->whereDoesntHave('akunDetail', function ($q) {
                $q->where('variabel_nilai', 'ppn_keluaran');
            });
        }

        // Filter berdasarkan akun pengembalian di sisi kredit (posisi = 'k')
        if ($akunPengembalian) {
            $query->whereHas('akunDetail', function ($q) use ($akunPengembalian) {
                $q->where('no_akun', $akunPengembalian)
                    ->where('posisi', 'k');
            });
        }

        $kitab = $query->with('akunDetail')->first();

        if ($kitab) {
            return $kitab;
        }

        // Fallback: jika akun pengembalian spesifik tidak cocok, ambil template retur aktif pertama yang sesuai PPN
        return BukuKitab::query()
            ->where('is_active', true)
            ->where('kode', 'like', 'retur_%')
            ->when($isPpn, function ($q) {
                $q->whereHas('akunDetail', fn ($sq) => $sq->where('variabel_nilai', 'ppn_keluaran'));
            }, function ($q) {
                $q->whereDoesntHave('akunDetail', fn ($sq) => $sq->where('variabel_nilai', 'ppn_keluaran'));
            })
            ->with('akunDetail')
            ->first();
    }

    /**
     * Tentukan kode Buku Kitab dari database secara dinamis.
     */
    public static function tentukanKodeKitab(
        bool $isPpn,
        ?string $akunPengembalian = null
    ): string {
        $kitab = self::cariBukuKitab($isPpn, $akunPengembalian);

        if ($kitab) {
            return $kitab->kode;
        }

        // Fallback terakhir jika database Buku Kitab kosong
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
        ?string $akunPengembalian = null,
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

        $akunPengembalian = $akunPengembalian ?: self::getDefaultAkunPengembalian();

        // Baca template Buku Kitab secara penuh dari database
        $kitab = self::cariBukuKitab($isPpn, $akunPengembalian);
        $kodeKitab = $kitab?->kode ?? self::tentukanKodeKitab($isPpn, $akunPengembalian);
        $namaKitab = $kitab?->nama ?? $kodeKitab;

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

            // Akun Persediaan displit per barang untuk engine BukuKitabJurnalService
            $breakdownPersediaan[] = [
                'id_barang' => $idBarang,
                'nama_barang' => $namaBarang,
                'banyak' => $qty,
                'harga' => $hargaBeli,
                'keterangan' => "Retur Masuk Stok: {$namaBarang}",
            ];

            // Akun HPP tidak displit per barang, cukup 1 agregat
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

        // Bangun preview simulasi baris jurnal membaca struktur akun langsung dari Buku Kitab
        $previewJurnal = [];
        if ($kitab) {
            $contextPreview = [
                'nilai_retur'     => $subtotalRetur,
                'ppn_keluaran'    => $ppnNominal,
                'hpp'             => $totalHpp,
                'nominal_kas'     => $totalNilaiRetur,
                'kewajiban_retur' => $totalNilaiRetur,
            ];

            foreach ($kitab->akunDetail as $b) {
                if (str_starts_with($b->variabel_nilai, 'persediaan_') || str_contains($b->variabel_nilai, 'persediaan')) {
                    $contextPreview[$b->variabel_nilai] = $totalHpp;
                }
                $nom = (float) ($contextPreview[$b->variabel_nilai] ?? 0);
                $pos = strtolower($b->posisi);

                $previewJurnal[] = [
                    'urut'           => $b->urut,
                    'no_akun'        => $b->no_akun,
                    'nama_akun'      => $b->nama_akun,
                    'posisi'         => strtoupper($pos),
                    'variabel_nilai' => $b->variabel_nilai,
                    'keterangan'     => $b->keterangan,
                    'debit'          => $pos === 'd' ? $nom : 0,
                    'kredit'         => $pos === 'k' ? $nom : 0,
                ];
            }
        }

        return [
            'is_dp'                => ($penjualan->jenis_transaksi === 'DP'),
            'is_retur_penuh'       => $isReturPenuh,
            'is_ppn'               => $isPpn,
            'jenis_retur'          => $jenisRetur,
            'kode_kitab'           => $kodeKitab,
            'nama_kitab'           => $namaKitab,
            'buku_kitab_id'        => $kitab?->id,
            'akun_pengembalian'    => $akunPengembalian,
            'subtotal_retur'       => $subtotalRetur,
            'ppn_nominal'          => $ppnNominal,
            'total_retur'          => $totalNilaiRetur,
            'total_hpp'            => $totalHpp,
            'breakdown_persediaan' => $breakdownPersediaan,
            'breakdown_hpp'        => $breakdownHpp,
            'preview_jurnal'       => $previewJurnal,
        ];
    }

    /**
     * Buat jurnal pembantu otomatis untuk transaksi retur penjualan.
     * Sepenuhnya membaca akun & variabel dari template Buku Kitab.
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
            akunPengembalian: $return->akun_pengembalian ?: self::getDefaultAkunPengembalian(),
            excludeReturnId: $return->id
        );

        $kodeKitab = $return->kode_kitab ?: $calc['kode_kitab'];

        // Cari atau validasi template Buku Kitab dari database
        $kitab = BukuKitab::where('kode', $kodeKitab)->with('akunDetail')->first()
            ?? self::cariBukuKitab($calc['is_ppn'], $return->akun_pengembalian);

        if (! $kitab) {
            throw new RuntimeException("Template Buku Kitab '{$kodeKitab}' tidak ditemukan atau tidak aktif.");
        }

        $kodeKitab = $kitab->kode;

        // Siapkan Context nilai secara dinamis dari variabel yang didefinisikan di Buku Kitab
        $context = [
            'nilai_retur'     => $calc['subtotal_retur'],
            'ppn_keluaran'    => $calc['ppn_nominal'],
            'hpp'             => $calc['total_hpp'],
            'nominal_kas'     => $calc['total_retur'],
            'kewajiban_retur' => $calc['total_retur'],
        ];

        $itemBreakdown = [
            'hpp' => $calc['breakdown_hpp'],
        ];

        $splitHeaderPerBarang = [];

        // Baca seluruh baris Buku Kitab untuk mengisi variabel dan item breakdown secara dinamis
        foreach ($kitab->akunDetail as $baris) {
            $var = $baris->variabel_nilai;
            if (! $var) {
                continue;
            }

            if (str_starts_with($var, 'persediaan_') || str_contains($var, 'persediaan')) {
                $context[$var] = $calc['total_hpp'];
                $itemBreakdown[$var] = $calc['breakdown_persediaan'];
                $splitHeaderPerBarang[] = $var;
            } elseif (in_array($var, ['nominal_kas', 'kewajiban_retur', 'piutang_usaha', 'dp_penjualan'], true)) {
                $context[$var] = $calc['total_retur'];
            } elseif ($var === 'nilai_retur') {
                $context[$var] = $calc['subtotal_retur'];
            } elseif ($var === 'ppn_keluaran') {
                $context[$var] = $calc['ppn_nominal'];
            } elseif ($var === 'hpp') {
                $context[$var] = $calc['total_hpp'];
            }
        }

        if (empty($splitHeaderPerBarang)) {
            $splitHeaderPerBarang = ['persediaan_barang_jadi'];
            $context['persediaan_barang_jadi'] = $calc['total_hpp'];
            $itemBreakdown['persediaan_barang_jadi'] = $calc['breakdown_persediaan'];
        } else {
            $splitHeaderPerBarang = array_values(array_unique($splitHeaderPerBarang));
        }

        $noDokumen = $return->no_retur ?: "RET-{$return->id} ({$return->no_nota})";

        DB::transaction(function () use (
            $kodeKitab,
            $context,
            $noDokumen,
            $return,
            $userId,
            $itemBreakdown,
            $splitHeaderPerBarang
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
                itemBreakdown: $itemBreakdown,
                splitHeaderPerBarang: $splitHeaderPerBarang,
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
