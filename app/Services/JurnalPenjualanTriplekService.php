<?php

namespace App\Services;

use App\Models\Penjualan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Jurnal Penjualan Triplek & Turunannya - berbasis template Buku Kitab.
 *
 *   - COD          : bayar = 0 -> Piutang PENUH + akui penjualan + stok keluar SEKARANG.
 *   - DP           : Piutang PENUH + akui penjualan + stok keluar SEKARANG, plus
 *                    pasangan Kas(D)/Uang Muka(K) untuk nominal yang sudah diterima.
 *   - BAYAR_DIMUKA : Kas masuk penuh + akui penjualan penuh sekaligus.
 *
 * Baris bernilai 0 di-skip oleh engine (BukuKitabJurnalService), sehingga split
 * Tunai+Transfer tidak menduplikasi baris Piutang/HPP/Penjualan/Persediaan/Hutang Gaji
 * (lihat postingSplitKas: semua context di-nol-kan di leg kedua kecuali nominal_kas).
 */
class JurnalPenjualanTriplekService
{
    private const HUTANG_GAJI_TETAP = 300000.0;

    private const KODE_PERSEDIAAN_FALLBACK = '1404.0';

    /**
     * Key context yang tetap dibuat di Jurnal Pembantu Header walau nilainya 0
     * (mis. harga_beli barang belum diisi). Di postingSplitKas hanya diteruskan
     * ke leg PERTAMA supaya split Tunai+Transfer tidak menduplikasi baris.
     */
    private const BARIS_TAMPIL_MESKI_NOL = ['persediaan_barang_jadi', 'hpp'];

    private function tambahKeteranganNota(string $default, Penjualan $penjualan): string
    {
        return filled($penjualan->keterangan)
            ? "{$default} - {$penjualan->keterangan}"
            : $default;
    }

    private function tambahKeteranganBayar(string $default, Penjualan $penjualan): string
    {
        return filled($penjualan->keterangan_pembayaran)
            ? "{$default} - {$penjualan->keterangan_pembayaran}"
            : $default;
    }

    public function __construct(
        private readonly BukuKitabJurnalService $engine,
    ) {}

    public function buatJurnalPenjualan(Penjualan $penjualan, int $userId, ?int $noJurnalOverride = null): void
    {
        $penjualan->loadMissing(['details.barang.subAnakAkun', 'rekeningPerusahaan.subAnakAkun']);

        $totalBayar = $this->totalBayar($penjualan);
        $data = $this->siapkanDataBarang($penjualan);

        DB::transaction(function () use ($penjualan, $userId, $totalBayar, $data, $noJurnalOverride) {
            $noJurnal = $noJurnalOverride ?: NomorJurnalService::nextJurnal();

            if ($penjualan->jenis_transaksi === 'DP') {
                $this->postingSplitKas(
                    prefix: 'penjualan_dp_diterima',
                    penjualan: $penjualan,
                    userId: $userId,
                    noJurnal: $noJurnal,
                    contextFull: [
                        'piutang_usaha' => (float) $penjualan->total,
                        'dp_penjualan' => $totalBayar,
                        'hpp' => $data['hpp'],
                        'ppn_keluaran' => $data['ppn_nominal'],
                        'nilai_penjualan' => (float) $penjualan->sub_total,
                        'persediaan_barang_jadi' => $data['nilai_pokok_barang'],
                        'hutang_gaji' => $data['hutang_gaji'],
                    ],
                    keteranganDefault: $this->tambahKeteranganBayar(
                        $this->tambahKeteranganNota('Penerimaan DP - Penjualan Diakui Penuh', $penjualan),
                        $penjualan,
                    ),
                    itemBreakdown: [
                        'persediaan_barang_jadi' => $data['breakdown_persediaan'],
                        'hpp' => $data['breakdown_hpp'],
                        'nilai_penjualan' => $data['breakdown_penjualan'],
                    ],
                    splitHeaderPerBarang: ['persediaan_barang_jadi', 'hpp', 'nilai_penjualan'],
                    tampilkanMeskiNol: self::BARIS_TAMPIL_MESKI_NOL,
                );

                return;
            }

            if ($penjualan->jenis_transaksi === 'BAYAR_DIMUKA') {
                if ($totalBayar > 0) {
                    $this->postingSplitKas(
                        prefix: 'penjualan_bayar_dimuka_diterima',
                        penjualan: $penjualan,
                        userId: $userId,
                        noJurnal: $noJurnal,
                        contextFull: ['dp_penjualan' => $totalBayar],
                        keteranganDefault: $this->tambahKeteranganBayar('Penerimaan Bayar Dimuka', $penjualan),
                    );
                }

                $this->engine->buatJurnalDariKitab(
                    kodeKitab: 'penjualan_bayar_dimuka_dikirim',
                    context: [
                        'dp_penjualan' => $totalBayar,
                        'hpp' => $data['hpp'],
                        'ppn_keluaran' => $data['ppn_nominal'],
                        'nilai_penjualan' => (float) $penjualan->sub_total,
                        'persediaan_barang_jadi' => $data['nilai_pokok_barang'],
                        'hutang_gaji' => $data['hutang_gaji'],
                    ],
                    noDokumen: $penjualan->no_nota,
                    tglTransaksi: $penjualan->tanggal,
                    modulAsal: 'penjualan_triplek',
                    jenisTransaksi: 'bk',
                    userId: $userId,
                    jenisPihak: 'pelanggan',
                    namaPihak: $penjualan->nama_customer ?: 'Pelanggan',
                    pihakId: $penjualan->pembeli_id,
                    keteranganDefault: $this->tambahKeteranganNota('Pengiriman Barang (Bayar Dimuka)', $penjualan),
                    itemBreakdown: [
                        'persediaan_barang_jadi' => $data['breakdown_persediaan'],
                        'hpp' => $data['breakdown_hpp'],
                        'nilai_penjualan' => $data['breakdown_penjualan'],
                    ],
                    splitHeaderPerBarang: ['persediaan_barang_jadi', 'hpp', 'nilai_penjualan'],
                    noJurnalOverride: $noJurnal,
                    tampilkanMeskiNol: self::BARIS_TAMPIL_MESKI_NOL,
                );

                return;
            }

            // -- COD: bayar = 0, langsung Piutang PENUH + akui penjualan --
            $kodeKitab = $data['ppn_nominal'] > 0 ? 'penjualan_triplek_ppn' : 'penjualan_triplek_non_ppn';

            $this->engine->buatJurnalDariKitab(
                kodeKitab: $kodeKitab,
                context: [
                    'piutang_usaha' => (float) $penjualan->total,
                    'hpp' => $data['hpp'],
                    'ppn_keluaran' => $data['ppn_nominal'],
                    'nilai_penjualan' => (float) $penjualan->sub_total,
                    'persediaan_barang_jadi' => $data['nilai_pokok_barang'],
                    'hutang_gaji' => $data['hutang_gaji'],
                ],
                noDokumen: $penjualan->no_nota,
                tglTransaksi: $penjualan->tanggal,
                modulAsal: 'penjualan_triplek',
                jenisTransaksi: 'bk',
                userId: $userId,
                jenisPihak: 'pelanggan',
                namaPihak: $penjualan->nama_customer ?: 'Pelanggan',
                pihakId: $penjualan->pembeli_id,
                keteranganDefault: $this->tambahKeteranganNota('Penjualan Triplek', $penjualan),
                itemBreakdown: [
                    'persediaan_barang_jadi' => $data['breakdown_persediaan'],
                    'hpp' => $data['breakdown_hpp'],
                    'nilai_penjualan' => $data['breakdown_penjualan'],
                ],
                splitHeaderPerBarang: ['persediaan_barang_jadi', 'hpp', 'nilai_penjualan'],
                noJurnalOverride: $noJurnal,
                tampilkanMeskiNol: self::BARIS_TAMPIL_MESKI_NOL,
            );
        });
    }

    /**
     * TAHAP PELUNASAN DP (belum dipanggil dari UI). 3 baris: Kas D, Uang Muka D, Piutang K.
     */
    public function buatJurnalPelunasanDp(Penjualan $penjualan, float $nominalDibayarSekarang, int $userId): void
    {
        $penjualan->loadMissing('rekeningPerusahaan.subAnakAkun');

        $dpSudahDiterima = $this->totalBayar($penjualan);

        DB::transaction(function () use ($penjualan, $nominalDibayarSekarang, $dpSudahDiterima, $userId) {
            $noJurnal = NomorJurnalService::nextJurnal();

            $this->postingSplitKas(
                prefix: 'penjualan_dp_pelunasan',
                penjualan: $penjualan,
                userId: $userId,
                noJurnal: $noJurnal,
                contextFull: [
                    'dp_penjualan' => $dpSudahDiterima,
                    'piutang_usaha' => (float) $penjualan->total,
                ],
                keteranganDefault: $this->tambahKeteranganBayar('Pelunasan Sisa Piutang DP', $penjualan),
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
            $subtotalDetail = (float) $detail->subtotal;
            $hargaJualSatuan = (float) ($detail->harga_jual ?? 0);

            $kubikasi = (float) ($detail->kubikasi ?? 0);
            $hitungM3 = ($detail->hitung_dari ?? 'qty') === 'm3' && $kubikasi > 0;
            $pengali = $hitungM3 ? $kubikasi : $qty;

            if ($hitungM3) {
                $infoM3 = ['m3' => $kubikasi, 'hit_kbk' => 'm'];
            } elseif ($kubikasi > 0) {
                $infoM3 = ['m3' => $kubikasi];
            } else {
                $infoM3 = [];
            }

            // Barang harga 0 (sampel/bonus/promo) -> HPP/persediaan 0.
            $isHargaNol = ($hargaJualSatuan <= 0 || $subtotalDetail <= 0);
            $hargaBeli = $isHargaNol ? 0.0 : (float) ($detail->barang->harga_beli ?? 0);
            $nominalHpp = $pengali * $hargaBeli;

            // Peringatan: barang dijual tapi harga_beli master kosong/0.
            if (! $isHargaNol && $hargaBeli <= 0) {
                Log::warning("[JurnalPenjualanTriplek] Barang '{$detail->nama_barang}' harga_beli = 0/kosong di master. Persediaan & HPP barang bernilai 0.");
            }

            $nilaiPokokBarang += $nominalHpp;

            $kodeAkun = $detail->barang?->subAnakAkun?->kode_sub_anak_akun;
            $namaAkun = $detail->barang?->subAnakAkun?->nama_sub_anak_akun;

            if (! $kodeAkun) {
                Log::warning("[JurnalPenjualanTriplek] Barang '{$detail->nama_barang}' belum di-set akun persediaannya. Menggunakan fallback '".self::KODE_PERSEDIAAN_FALLBACK."'.");
                $kodeAkun = self::KODE_PERSEDIAAN_FALLBACK;
                $namaAkun = '[!] Akun persediaan belum di-set: '.$detail->nama_barang;
            }

            $breakdownPersediaan[] = [
                'no_akun' => $kodeAkun,
                'nama_akun' => $namaAkun,
                'id_barang' => $detail->barang_id,
                'nama_barang' => $detail->nama_barang,
                'banyak' => $qty,
                'harga' => $hargaBeli,
                'keterangan' => 'Keluar stok '.$detail->nama_barang,
            ] + $infoM3;

            $kodeAkunHpp = $detail->barang?->akunHpp?->kode_sub_anak_akun;
            $namaAkunHpp = $detail->barang?->akunHpp?->nama_sub_anak_akun;

            $breakdownHpp[] = [
                'no_akun' => $kodeAkunHpp,
                'nama_akun' => $namaAkunHpp,
                'nama_barang' => $detail->nama_barang,
                'banyak' => $qty,
                'harga' => $hargaBeli,
                'keterangan' => 'HPP '.$detail->nama_barang,
            ] + $infoM3;

            $kodeAkunPendapatan = $detail->barang?->akunPendapatan?->kode_sub_anak_akun;
            $namaAkunPendapatan = $detail->barang?->akunPendapatan?->nama_sub_anak_akun;

            $hargaJualBersih = $pengali > 0 ? round($subtotalDetail / $pengali, 4) : 0;

            $breakdownPenjualan[] = [
                'no_akun' => $kodeAkunPendapatan,
                'nama_akun' => $namaAkunPendapatan,
                'nama_barang' => $detail->nama_barang,
                'banyak' => $qty,
                'harga' => $hargaJualBersih,
                'keterangan' => 'Penjualan '.$detail->nama_barang,
            ] + $infoM3;
        }

        $hutangGaji = self::HUTANG_GAJI_TETAP;

        $breakdownHpp[] = [
            'nama_barang' => null,
            'banyak' => 1,
            'harga' => $hutangGaji,
            'keterangan' => 'Alokasi Hutang Gaji',
        ];

        return [
            'breakdown_persediaan' => $breakdownPersediaan,
            'breakdown_hpp' => $breakdownHpp,
            'breakdown_penjualan' => $breakdownPenjualan,
            'nilai_pokok_barang' => $nilaiPokokBarang,
            'hutang_gaji' => $hutangGaji,
            'hpp' => $nilaiPokokBarang + $hutangGaji,
            'ppn_nominal' => (float) $penjualan->ppn_nominal,
        ];
    }

    private function totalBayar(Penjualan $penjualan): float
    {
        return $penjualan->metode_pembayaran === 'TUNAI & TRANSFER'
            ? (float) $penjualan->bayar_tunai + (float) $penjualan->bayar_transfer
            : (float) $penjualan->bayar;
    }

    /**
     * Posting kas yang bisa pecah ke Tunai + Transfer. Semua key $contextFull
     * di-nol-kan di leg KEDUA dst supaya baris tidak terduplikasi.
     *
     * @param  float|null  $nominalKasOverride  Kalau diisi, dipakai sebagai total kas
     *                                          yang mau dipecah (dipakai buatJurnalPelunasanDp()).
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
        array $tampilkanMeskiNol = [],
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
                pihakId: $penjualan->pembeli_id,
                keteranganDefault: $keteranganDefault,
                itemBreakdown: $itemBreakdown,
                splitHeaderPerBarang: $splitHeaderPerBarang,
                noJurnalOverride: $noJurnal,
                catatanPerVariabel: [
                    'nominal_kas' => trim((string) ($penjualan->keterangan_pembayaran ?? '')),
                ],
                tampilkanMeskiNol: $sudahAdaLeg ? [] : $tampilkanMeskiNol,
            );

            $sudahAdaLeg = true;
        }

        if (! $sudahAdaLeg) {
            throw new \RuntimeException(
                "Tidak ada kas yang diterima untuk transaksi {$penjualan->no_nota} - cek input pembayaran."
            );
        }
    }

    /** @return array<int, array{0: string, 1: float}> */
    private function resolveKakiKas(Penjualan $penjualan, string $prefix, ?float $nominalKasOverride = null): array
    {
        if ($nominalKasOverride !== null) {
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
                "Rekening bank untuk transaksi {$penjualan->no_nota} tidak ditemukan - ".
                'tidak bisa menentukan kode Buku Kitab yang sesuai.'
            );
        }

        return strtolower(str_replace(' ', '_', trim($namaAkun)));
    }
}
