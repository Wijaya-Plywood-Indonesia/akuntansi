<?php

namespace App\Services;

use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\Penjualan;
use Illuminate\Support\Facades\DB;

/**
 * Jurnal Penjualan Triplek & Turunannya — berbasis template Buku Kitab.
 *
 * SEMUA jenis_transaksi (COD/DP/BAYAR_DIMUKA) lewat method
 * buatJurnalPenjualan(), tapi behaviornya BEDA WAKTU pengakuannya:
 *
 *   - COD          : bayar = 0 -> langsung Piutang PENUH + akui penjualan
 *                    + kurangi stok SEKARANG (barang keluar sekarang).
 *   - DP           : HANYA catat Uang Muka diterima (Kas D, Uang Muka K).
 *                    TIDAK ada pengakuan penjualan, TIDAK ada pengurangan
 *                    stok di titik ini — itu baru terjadi NANTI di menu
 *                    Pelunasan (lihat JurnalPelunasanDpTriplekService, kalau
 *                    sudah dibuat), pakai kitab penjualan_dp_pelunasan_*.
 *   - BAYAR_DIMUKA : Kas masuk PENUH + Uang Muka (K), LALU (di klik yang
 *                    sama) Uang Muka dibalik + HPP/PPN/Penjualan/Persediaan/
 *                    Hutang Gaji diakui + stok berkurang SEKARANG — karena
 *                    di kasus ini barang memang dianggap langsung dikirim.
 */
class JurnalPenjualanTriplekService
{
    private const HUTANG_GAJI_TETAP = 300000.0;

    public function __construct(
        private readonly BukuKitabJurnalService $engine,
    ) {}

    public function buatJurnalPenjualan(Penjualan $penjualan, int $userId): void
    {
        $penjualan->loadMissing(['details.barang', 'rekeningPerusahaan.subAnakAkun']);

        $totalBayar = $this->totalBayar($penjualan);

        DB::transaction(function () use ($penjualan, $userId, $totalBayar) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            if ($penjualan->jenis_transaksi === 'DP') {
                // ── DP: HANYA Tahap 1 (uang muka diterima) ──────────────
                // 2 baris saja: Kas/Bank (D) & Uang Muka Pelanggan (K).
                // TIDAK ada HPP/Penjualan/Persediaan di sini — itu baru
                // terjadi nanti saat Pelunasan.
                $this->postingSplitKas(
                    prefix: 'penjualan_dp_diterima',
                    penjualan: $penjualan,
                    userId: $userId,
                    noJurnal: $noJurnal,
                    contextFull: ['dp_penjualan' => $totalBayar],
                    keteranganDefault: 'Penerimaan DP Penjualan',
                );

                return; // STOP di sini untuk DP.
            }

            $data = $this->siapkanDataBarang($penjualan);

            if ($penjualan->jenis_transaksi === 'BAYAR_DIMUKA') {
                // ── BAYAR_DIMUKA: Tahap 1 + Tahap 2 sekaligus ───────────
                if ($totalBayar > 0) {
                    $this->postingSplitKas(
                        prefix: 'penjualan_bayar_dimuka_diterima',
                        penjualan: $penjualan,
                        userId: $userId,
                        noJurnal: $noJurnal,
                        contextFull: ['dp_penjualan' => $totalBayar],
                        keteranganDefault: 'Penerimaan Bayar Dimuka',
                    );
                }

                $this->engine->buatJurnalDariKitab(
                    kodeKitab: 'penjualan_bayar_dimuka_dikirim',
                    context: [
                        'dp_penjualan'           => $totalBayar,
                        'hpp'                    => $data['hpp'],
                        'ppn_keluaran'           => $data['ppn_nominal'],
                        'nilai_penjualan'        => (float) $penjualan->sub_total,
                        'persediaan_barang_jadi' => $data['nilai_pokok_barang'],
                        'hutang_gaji'            => $data['hutang_gaji'],
                    ],
                    noDokumen: $penjualan->no_nota,
                    tglTransaksi: $penjualan->tanggal,
                    modulAsal: 'penjualan_triplek',
                    jenisTransaksi: 'bk',
                    userId: $userId,
                    jenisPihak: 'pelanggan',
                    namaPihak: $penjualan->nama_customer ?: 'Pelanggan',
                    keteranganDefault: 'Pengiriman Barang (Bayar Dimuka)',
                    itemBreakdown: [
                        'persediaan_barang_jadi' => $data['breakdown_persediaan'],
                        'hpp'                    => $data['breakdown_hpp'],
                        'nilai_penjualan'        => $data['breakdown_penjualan'],
                    ],
                    splitHeaderPerBarang: ['persediaan_barang_jadi'],
                    noJurnalOverride: $noJurnal,
                );

                return;
            }

            // ── COD: bayar = 0, langsung Piutang PENUH + akui penjualan ──
            $kodeKitab = $data['ppn_nominal'] > 0 ? 'penjualan_triplek_ppn' : 'penjualan_triplek_non_ppn';

            $this->engine->buatJurnalDariKitab(
                kodeKitab: $kodeKitab,
                context: [
                    'piutang_usaha'          => (float) $penjualan->total,
                    'hpp'                    => $data['hpp'],
                    'ppn_keluaran'           => $data['ppn_nominal'],
                    'nilai_penjualan'        => (float) $penjualan->sub_total,
                    'persediaan_barang_jadi' => $data['nilai_pokok_barang'],
                    'hutang_gaji'            => $data['hutang_gaji'],
                ],
                noDokumen: $penjualan->no_nota,
                tglTransaksi: $penjualan->tanggal,
                modulAsal: 'penjualan_triplek',
                jenisTransaksi: 'bk',
                userId: $userId,
                jenisPihak: 'pelanggan',
                namaPihak: $penjualan->nama_customer ?: 'Pelanggan',
                keteranganDefault: 'Penjualan Triplek',
                itemBreakdown: [
                    'persediaan_barang_jadi' => $data['breakdown_persediaan'],
                    'hpp'                    => $data['breakdown_hpp'],
                    'nilai_penjualan'        => $data['breakdown_penjualan'],
                ],
                splitHeaderPerBarang: ['persediaan_barang_jadi'],
                noJurnalOverride: $noJurnal,
            );
        });
    }

    /* =====================================================================
     * INTERNAL
     * ===================================================================== */

    private function siapkanDataBarang(Penjualan $penjualan): array
    {
        $breakdownPersediaan = [];
        $breakdownHpp = [];
        $breakdownPenjualan = [];
        $nilaiPokokBarang = 0.0;

        foreach ($penjualan->details as $detail) {
            $qty = (float) $detail->qty;
            $hargaBeli = (float) ($detail->barang->harga_beli ?? 0);
            $nominal = $qty * $hargaBeli;

            if ($nominal <= 0) {
                continue;
            }

            $nilaiPokokBarang += $nominal;

            $breakdownPersediaan[] = [
                'id_barang'   => $detail->barang_id,
                'nama_barang' => $detail->nama_barang,
                'banyak'      => $qty,
                'harga'       => $hargaBeli,
                'keterangan'  => 'Keluar stok '.$detail->nama_barang,
            ];

            $breakdownHpp[] = [
                'nama_barang' => $detail->nama_barang,
                'banyak'      => $qty,
                'harga'       => $hargaBeli,
                'keterangan'  => 'HPP '.$detail->nama_barang,
            ];

            $hargaJualBersih = $qty > 0 ? round((float) $detail->subtotal / $qty, 4) : 0;
            $breakdownPenjualan[] = [
                'nama_barang' => $detail->nama_barang,
                'banyak'      => $qty,
                'harga'       => $hargaJualBersih,
                'keterangan'  => 'Penjualan '.$detail->nama_barang,
            ];
        }

        $hutangGaji = self::HUTANG_GAJI_TETAP;

        $breakdownHpp[] = [
            'nama_barang' => null,
            'banyak'      => 1,
            'harga'       => $hutangGaji,
            'keterangan'  => 'Alokasi Hutang Gaji',
        ];

        return [
            'breakdown_persediaan' => $breakdownPersediaan,
            'breakdown_hpp'        => $breakdownHpp,
            'breakdown_penjualan'  => $breakdownPenjualan,
            'nilai_pokok_barang'   => $nilaiPokokBarang,
            'hutang_gaji'          => $hutangGaji,
            'hpp'                  => $nilaiPokokBarang + $hutangGaji,
            'ppn_nominal'          => (float) $penjualan->ppn_nominal,
        ];
    }

    private function totalBayar(Penjualan $penjualan): float
    {
        return $penjualan->metode_pembayaran === 'TUNAI & TRANSFER'
            ? (float) $penjualan->bayar_tunai + (float) $penjualan->bayar_transfer
            : (float) $penjualan->bayar;
    }

    /**
     * Posting kas yang bisa pecah ke Tunai + Transfer sekaligus, lewat
     * kitab "{prefix}_tunai" / "{prefix}_bank_xxx". nominal_kas & baris
     * lain di $contextFull (mis. dp_penjualan) hanya diisi PENUH di leg
     * PERTAMA; leg kedua (kalau ada split) di-nol-kan supaya tidak dobel.
     */
    private function postingSplitKas(
        string $prefix,
        Penjualan $penjualan,
        int $userId,
        int $noJurnal,
        array $contextFull,
        string $keteranganDefault,
    ): void {
        $legs = $this->resolveKakiKas($penjualan, $prefix);
        $sudahAdaLeg = false;

        foreach ($legs as [$kodeKitab, $nominalKas]) {
            if ($nominalKas <= 0 && count($legs) > 1) {
                continue;
            }

            $context = $sudahAdaLeg
                ? array_fill_keys(array_keys($contextFull), 0)
                : $contextFull;
            $context['nominal_kas'] = $nominalKas;

            $this->engine->buatJurnalDariKitab(
                kodeKitab: $kodeKitab,
                context: $context,
                noDokumen: $penjualan->no_nota,
                tglTransaksi: $penjualan->tanggal,
                modulAsal: 'penjualan_triplek',
                jenisTransaksi: 'bk',
                userId: $userId,
                jenisPihak: 'pelanggan',
                namaPihak: $penjualan->nama_customer ?: 'Pelanggan',
                keteranganDefault: $keteranganDefault,
                noJurnalOverride: $noJurnal,
            );

            $sudahAdaLeg = true;
        }

        if (! $sudahAdaLeg) {
            throw new \RuntimeException(
                "Tidak ada kas yang diterima untuk transaksi {$penjualan->no_nota} — cek input pembayaran."
            );
        }
    }

    /** @return array<int, array{0: string, 1: float}> */
    private function resolveKakiKas(Penjualan $penjualan, string $prefix): array
    {
        return match ($penjualan->metode_pembayaran) {
            'TUNAI & TRANSFER' => [
                [$prefix.'_tunai', (float) $penjualan->bayar_tunai],
                [$prefix.'_'.$this->slugBank($penjualan), (float) $penjualan->bayar_transfer],
            ],
            'TRANSFER' => [
                [$prefix.'_'.$this->slugBank($penjualan), $this->totalBayar($penjualan)],
            ],
            default => [
                [$prefix.'_tunai', $this->totalBayar($penjualan)],
            ],
        };
    }

    private function slugBank(Penjualan $penjualan): string
    {
        $namaAkun = $penjualan->rekeningPerusahaan?->nama_akun
            ?? $penjualan->rekeningPerusahaan?->subAnakAkun?->nama_sub_anak_akun;

        if (blank($namaAkun)) {
            throw new \RuntimeException(
                "Rekening bank untuk transaksi {$penjualan->no_nota} tidak ditemukan — ".
                'tidak bisa menentukan kode Buku Kitab yang sesuai.'
            );
        }

        return strtolower(str_replace(' ', '_', trim($namaAkun)));
    }
}