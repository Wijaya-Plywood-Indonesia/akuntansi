<?php

namespace App\Services;

use App\Models\JurnalPembantuHeader;
use App\Models\Penjualan;
use Illuminate\Support\Facades\DB;

/**
 * Jurnal Penjualan Triplek & Turunannya — berbasis template Buku Kitab.
 *
 * REVISI STRUKTUR DP (lihat README_BUKU_KITAB.md & riwayat chat):
 * Sebelumnya DP cuma catat Uang Muka (2 baris), penjualan ditunda ke tahap
 * Pelunasan. SEKARANG penjualan diakui PENUH langsung saat DP diterima
 * (mirip COD), bedanya cuma ada tambahan pasangan Kas(D)/Uang Muka(K)
 * untuk mencatat berapa yang benar-benar sudah masuk. Tahap Pelunasan
 * jadi jauh lebih ringkas: cuma menyelesaikan sisa kas & menutup Piutang.
 *
 *   - COD          : bayar = 0 -> Piutang PENUH + akui penjualan + stok
 *                    keluar SEKARANG.
 *   - DP           : sebagian bayar -> Piutang PENUH + akui penjualan +
 *                    stok keluar SEKARANG JUGA, plus pasangan Kas(D)/Uang
 *                    Muka(K) untuk nominal yang sudah diterima. Piutang
 *                    tetap dicatat PENUH (bukan dikurangi DP) — nanti di
 *                    Pelunasan, Uang Muka dipakai untuk menutup sebagian
 *                    Piutang itu.
 *   - BAYAR_DIMUKA : sama seperti sebelumnya — Kas masuk penuh + akui
 *                    penjualan penuh sekaligus.
 *
 * Baris bernilai 0 otomatis di-skip oleh engine, jadi split Tunai+Transfer
 * tidak menduplikasi baris Piutang/HPP/Penjualan/Persediaan/Hutang Gaji
 * (lihat postingSplitKas: semua context di-nol-kan di leg kedua kecuali
 * nominal_kas).
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
        $data = $this->siapkanDataBarang($penjualan);

        DB::transaction(function () use ($penjualan, $userId, $totalBayar, $data) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            if ($penjualan->jenis_transaksi === 'DP') {
                // ── DP: penjualan diakui PENUH sekarang, Piutang = TOTAL
                //    penuh (bukan dikurangi DP) + catat Kas/Uang Muka untuk
                //    nominal yang sudah masuk. Sisa penyelesaian ada di
                //    tahap Pelunasan nanti (buatJurnalPelunasanDp).
                $this->postingSplitKas(
                    prefix: 'penjualan_dp_diterima',
                    penjualan: $penjualan,
                    userId: $userId,
                    noJurnal: $noJurnal,
                    contextFull: [
                        'piutang_usaha'          => (float) $penjualan->total,
                        'dp_penjualan'           => $totalBayar,
                        'hpp'                    => $data['hpp'],
                        'nilai_penjualan'        => (float) $penjualan->sub_total,
                        'persediaan_barang_jadi' => $data['nilai_pokok_barang'],
                        'hutang_gaji'            => $data['hutang_gaji'],
                    ],
                    keteranganDefault: 'Penerimaan DP — Penjualan Diakui Penuh',
                    itemBreakdown: [
                        'persediaan_barang_jadi' => $data['breakdown_persediaan'],
                        'hpp'                    => $data['breakdown_hpp'],
                        'nilai_penjualan'        => $data['breakdown_penjualan'],
                    ],
                    splitHeaderPerBarang: ['persediaan_barang_jadi'],
                );

                return;
            }

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

    /**
     * TAHAP PELUNASAN DP — belum dipanggil dari UI manapun (menu Pelunasan
     * belum dibuat), disiapkan lebih dulu supaya siap pakai begitu menunya
     * ada. Sekarang cuma 3 baris (Kas D, Uang Muka D, Piutang K) karena
     * penjualan sudah diakui penuh di tahap "diterima" di atas.
     *
     * @param  float  $nominalDibayarSekarang  Sisa kas yang diterima saat pelunasan
     *                                          (biasanya = $penjualan->total - $penjualan->bayar,
     *                                          tapi dibiarkan parameter terpisah untuk fleksibilitas
     *                                          kalau ternyata dibayar bertahap lagi).
     */
    public function buatJurnalPelunasanDp(Penjualan $penjualan, float $nominalDibayarSekarang, int $userId): void
    {
        $penjualan->loadMissing('rekeningPerusahaan.subAnakAkun');

        $dpSudahDiterima = $this->totalBayar($penjualan);

        DB::transaction(function () use ($penjualan, $nominalDibayarSekarang, $dpSudahDiterima, $userId) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            $this->postingSplitKas(
                prefix: 'penjualan_dp_pelunasan',
                penjualan: $penjualan,
                userId: $userId,
                noJurnal: $noJurnal,
                contextFull: [
                    'dp_penjualan'  => $dpSudahDiterima,
                    'piutang_usaha' => (float) $penjualan->total,
                ],
                keteranganDefault: 'Pelunasan Sisa Piutang DP',
                nominalKasOverride: $nominalDibayarSekarang,
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
     * kitab "{prefix}_tunai" / "{prefix}_bank_xxx". SEMUA key di
     * $contextFull (bukan cuma dp_penjualan) di-nol-kan di leg KEDUA dst,
     * supaya baris Piutang/HPP/Penjualan/Persediaan/Hutang Gaji (kalau ada
     * di $contextFull) tidak ke-duplikat waktu split Tunai+Transfer.
     * $itemBreakdown & $splitHeaderPerBarang aman diteruskan ke setiap leg
     * apa adanya — otomatis "mati" di leg yang context-nya sudah di-nol-kan,
     * karena engine skip baris bernilai 0 SEBELUM sempat melihat breakdown.
     *
     * @param  float|null  $nominalKasOverride  Kalau diisi, dipakai sebagai
     *         total kas yang mau dipecah (bukan dari $penjualan->bayar) —
     *         dipakai buatJurnalPelunasanDp() untuk kirim nominal pelunasan
     *         yang beda dari nominal DP yang sudah tercatat sebelumnya.
     */
    private function postingSplitKas(
        string $prefix,
        Penjualan $penjualan,
        int $userId,
        int $noJurnal,
        array $contextFull,
        string $keteranganDefault,
        array $itemBreakdown = [],
        array $splitHeaderPerBarang = [],
        ?float $nominalKasOverride = null,
    ): void {
        $legs = $this->resolveKakiKas($penjualan, $prefix, $nominalKasOverride);
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
                itemBreakdown: $itemBreakdown,
                splitHeaderPerBarang: $splitHeaderPerBarang,
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
    private function resolveKakiKas(Penjualan $penjualan, string $prefix, ?float $nominalKasOverride = null): array
    {
        if ($nominalKasOverride !== null) {
            // Dipakai buatJurnalPelunasanDp() — metode bayar pelunasan
            // memakai field yang sama (metode_pembayaran/bayar_tunai/
            // bayar_transfer) tapi nominalnya beda dari saat DP diterima.
            return match ($penjualan->metode_pembayaran) {
                'TRANSFER' => [[$prefix.'_'.$this->slugBank($penjualan), $nominalKasOverride]],
                default => [[$prefix.'_tunai', $nominalKasOverride]],
            };
        }

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