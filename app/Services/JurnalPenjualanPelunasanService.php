<?php

namespace App\Services;

use App\Models\JurnalPembantuHeader;
use App\Models\Penjualan;
use App\Models\RekeningPerusahaan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Jurnal Pelunasan Piutang Penjualan — dipakai saat nota COD/DP dibayar
 * belakangan lewat menu Pelunasan (lihat PenjualanPelunasanService).
 *
 * Pakai kitab existing 'pelunasan_triplek_non_ppn_*' (2 baris: Kas D +
 * Piutang K per kitab). Kitab dipilih berdasarkan kode_sub_anak_akun
 * rekening (BUKAN nama rekening/bank — sudah terbukti tidak selalu match,
 * mis. rekening "Eddy Angkawijaya" ternyata terhubung ke akun "Bank 99").
 *
 * === Kasus DP ===
 * Nota jenis DP wajib dilunasi sekaligus penuh (lihat guard di
 * PenjualanPelunasanService::prosesPelunasan). Saat nota DP dibuat, DP
 * awal sudah tercatat sebagai liability 'Uang Muka Pelanggan' (2203.1).
 * Begitu piutang benar-benar lunas, uang muka itu harus direklasifikasi
 * jadi pengurang Piutang Usaha, bukan nongkrong terus sebagai liability.
 *
 * Untuk itu dipakai kitab varian '_dp' (3 baris: Kas/Bank D, Uang Muka
 * Pelanggan D, Piutang Usaha K) yang dipetakan lewat
 * KODE_KITAB_TUNAI_DP / MAP_KITAB_BANK_DP. Baris Piutang K nilainya
 * = nominal yang dibayar sekarang + DP awal yang direklas.
 *
 * Metode CAMPUR (split tunai + transfer) memanggil 2 kitab sekaligus
 * dalam 1 noJurnal (kitab tunai + kitab bank), yang masing-masing
 * menulis baris Piutang Usaha-nya sendiri. Untuk DP+CAMPUR, reklas Uang
 * Muka HANYA ditempel ke salah satu dari 2 kitab tsb (bukan dua-duanya)
 * supaya tidak dobel — lihat buatJurnalPelunasanCampur(). Setelah itu,
 * baris-baris Piutang hasil kedua kitab di-merge (lihat
 * mergeBarisPiutang()) jadi 1 baris gabungan supaya laporan jurnal tetap
 * rapi (1 baris Piutang per pelunasan, senilai total tunai + transfer +
 * DP yang direklas).
 */
class JurnalPenjualanPelunasanService
{
    private const KODE_KITAB_TUNAI = 'pelunasan_triplek_non_ppn_tunai';

    private const KODE_KITAB_TUNAI_DP = 'penjualan_dp_pelunasan_tunai';

    /**
     * Mapping kode_sub_anak_akun rekening -> kode kitab pelunasan bank.
     * Dikonfirmasi langsung dari no_akun baris Kas tiap kitab, BUKAN dari
     * nama rekening/bank (lihat catatan di atas).
     */
    private const MAP_KITAB_BANK = [
        '1101.3' => 'pelunasan_triplek_non_ppn_bank_99',
        '1101.4' => 'pelunasan_triplek_non_ppn_bank_wahana',
        '1101.5' => 'pelunasan_triplek_non_ppn_bank_wpi',
        '1101.6' => 'pelunasan_triplek_non_ppn_bank_industri',
        '1101.7' => 'pelunasan_triplek_non_ppn_bank_intan',
        '1101.8' => 'pelunasan_triplek_non_ppn_bank_bu_eddy',
    ];

    /**
     * Sama seperti MAP_KITAB_BANK, tapi varian kitab yang punya baris
     * tambahan Uang Muka Pelanggan (D) untuk reklasifikasi DP. Dipakai
     * kalau nota yang dilunasi berjenis DP.
     */
    private const MAP_KITAB_BANK_DP = [
        '1101.3' => 'penjualan_dp_pelunasan_bank_99',
        '1101.4' => 'penjualan_dp_pelunasan_bank_wahana',
        '1101.5' => 'penjualan_dp_pelunasan_bank_wpi',
        '1101.6' => 'penjualan_dp_pelunasan_bank_industri',
        '1101.7' => 'penjualan_dp_pelunasan_bank_intan',
        '1101.8' => 'penjualan_dp_pelunasan_bank_bu_eddy',
    ];

    public function __construct(
        private readonly BukuKitabJurnalService $engine,
    ) {}

    /**
     * Bangun jurnal pelunasan untuk pembayaran TUNAI.
     *
     * @param  int|null  $dpAwal  DP awal yang harus direklas dari Uang Muka
     *                            Pelanggan ke Piutang Usaha. Null/0 kalau
     *                            nota bukan jenis DP.
     */
    public function buatJurnalPelunasanTunai(Penjualan $nota, int $userId, float $nominal, ?int $dpAwal = null): void
    {
        if ($nominal <= 0) {
            return;
        }

        $kodeKitab = $dpAwal ? self::KODE_KITAB_TUNAI_DP : self::KODE_KITAB_TUNAI;

        $this->jalankanKitab(
            kodeKitab: $kodeKitab,
            nota: $nota,
            userId: $userId,
            nominal: $nominal,
            piutang: $nominal + (float) $dpAwal,
            dpAwal: $dpAwal,
            keteranganDefault: 'Pelunasan Piutang Penjualan (Tunai)'.($dpAwal ? ' + Reklas Uang Muka Pelanggan' : ''),
        );
    }

    /**
     * Bangun jurnal pelunasan untuk pembayaran TRANSFER via rekening tertentu.
     *
     * @param  int|null  $dpAwal  DP awal yang harus direklas dari Uang Muka
     *                            Pelanggan ke Piutang Usaha. Null/0 kalau
     *                            nota bukan jenis DP.
     *
     * @throws RuntimeException Kalau rekening tidak punya akun yang
     *                          terpetakan ke kitab pelunasan bank manapun.
     */
    public function buatJurnalPelunasanTransfer(
        Penjualan $nota,
        int $userId,
        float $nominal,
        RekeningPerusahaan $rekening,
        ?int $dpAwal = null,
    ): void {
        if ($nominal <= 0) {
            return;
        }

        $kodeAkun = $rekening->kodeAkun();
        $map = $dpAwal ? self::MAP_KITAB_BANK_DP : self::MAP_KITAB_BANK;
        $kodeKitab = $kodeAkun ? ($map[$kodeAkun] ?? null) : null;

        if (! $kodeKitab) {
            throw new RuntimeException(
                "Rekening '{$rekening->atas_nama}' (akun: ".($kodeAkun ?: '-').') belum punya kitab pelunasan'.
                ($dpAwal ? ' DP' : '').' yang terpetakan. '.
                'Hubungi admin untuk melengkapi '.($dpAwal ? 'MAP_KITAB_BANK_DP' : 'MAP_KITAB_BANK').' di JurnalPenjualanPelunasanService.'
            );
        }

        $this->jalankanKitab(
            kodeKitab: $kodeKitab,
            nota: $nota,
            userId: $userId,
            nominal: $nominal,
            piutang: $nominal + (float) $dpAwal,
            dpAwal: $dpAwal,
            keteranganDefault: "Pelunasan Piutang Penjualan (Transfer {$rekening->namaAkun()})".($dpAwal ? ' + Reklas Uang Muka Pelanggan' : ''),
        );
    }

    /**
     * Bangun jurnal pelunasan untuk pembayaran CAMPUR (split tunai + transfer).
     * Memanggil 2 kitab (tunai + bank) sekaligus dalam 1 noJurnal, lalu
     * meng-merge 2 baris Piutang Usaha hasil kedua kitab jadi 1 baris
     * gabungan (lihat mergeBarisPiutang()).
     *
     * Kalau nota berjenis DP ($dpAwal terisi), reklas Uang Muka Pelanggan
     * HANYA ditempel ke salah satu porsi (prioritas: porsi tunai kalau
     * ada, kalau tidak ke porsi transfer) supaya DP awal tidak kereklas
     * dua kali. Baris Piutang hasil kedua porsi akan tetap ke-merge jadi
     * 1 baris oleh mergeBarisPiutang(), jadi nilainya tetap benar
     * (nominalTunai + nominalTransfer + dpAwal).
     *
     * @param  int|null  $dpAwal  DP awal yang harus direklas dari Uang Muka
     *                            Pelanggan ke Piutang Usaha. Null/0 kalau
     *                            nota bukan jenis DP.
     *
     * @throws RuntimeException Kalau rekening tidak punya akun yang
     *                          terpetakan ke kitab pelunasan bank manapun.
     */
    public function buatJurnalPelunasanCampur(
        Penjualan $nota,
        int $userId,
        float $nominalTunai,
        float $nominalTransfer,
        RekeningPerusahaan $rekening,
        ?int $dpAwal = null,
    ): void {
        if ($nominalTunai <= 0 && $nominalTransfer <= 0) {
            return;
        }

        $kodeAkun = $rekening->kodeAkun();
        $mapBank = $dpAwal ? self::MAP_KITAB_BANK_DP : self::MAP_KITAB_BANK;
        $kodeKitabBank = $kodeAkun ? ($mapBank[$kodeAkun] ?? null) : null;

        if ($nominalTransfer > 0 && ! $kodeKitabBank) {
            throw new RuntimeException(
                "Rekening '{$rekening->atas_nama}' (akun: ".($kodeAkun ?: '-').') belum punya kitab pelunasan'.
                ($dpAwal ? ' DP' : '').' yang terpetakan. '.
                'Hubungi admin untuk melengkapi '.($dpAwal ? 'MAP_KITAB_BANK_DP' : 'MAP_KITAB_BANK').' di JurnalPenjualanPelunasanService.'
            );
        }

        // Reklas DP hanya ditempel ke SATU porsi saja supaya tidak dobel.
        // Prioritas: porsi tunai kalau ada, kalau tidak baru porsi transfer.
        $dpUntukTunai = ($dpAwal && $nominalTunai > 0) ? $dpAwal : null;
        $dpUntukTransfer = ($dpAwal && $nominalTunai <= 0 && $nominalTransfer > 0) ? $dpAwal : null;

        $kodeKitabTunai = $dpUntukTunai ? self::KODE_KITAB_TUNAI_DP : self::KODE_KITAB_TUNAI;

        DB::transaction(function () use (
            $nota,
            $userId,
            $nominalTunai,
            $nominalTransfer,
            $kodeKitabTunai,
            $kodeKitabBank,
            $rekening,
            $dpUntukTunai,
            $dpUntukTransfer,
        ) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            if ($nominalTunai > 0) {
                $context = [
                    'nominal_kas' => $nominalTunai,
                    'piutang_usaha' => $nominalTunai + (float) $dpUntukTunai,
                ];

                if ($dpUntukTunai) {
                    $context['dp_penjualan'] = (float) $dpUntukTunai;
                }

                $this->engine->buatJurnalDariKitab(
                    kodeKitab: $kodeKitabTunai,
                    context: $context,
                    noDokumen: $nota->no_nota,
                    tglTransaksi: now(),
                    modulAsal: 'pelunasan_penjualan',
                    jenisTransaksi: 'bk',
                    userId: $userId,
                    jenisPihak: 'pelanggan',
                    namaPihak: $nota->nama_customer ?: 'Pelanggan',
                    keteranganDefault: 'Pelunasan Piutang Penjualan (Tunai, split)'.($dpUntukTunai ? ' + Reklas Uang Muka Pelanggan' : ''),
                    noJurnalOverride: $noJurnal,
                );
            }

            if ($nominalTransfer > 0) {
                $context = [
                    'nominal_kas' => $nominalTransfer,
                    'piutang_usaha' => $nominalTransfer + (float) $dpUntukTransfer,
                ];

                if ($dpUntukTransfer) {
                    $context['dp_penjualan'] = (float) $dpUntukTransfer;
                }

                $this->engine->buatJurnalDariKitab(
                    kodeKitab: $kodeKitabBank,
                    context: $context,
                    noDokumen: $nota->no_nota,
                    tglTransaksi: now(),
                    modulAsal: 'pelunasan_penjualan',
                    jenisTransaksi: 'bk',
                    userId: $userId,
                    jenisPihak: 'pelanggan',
                    namaPihak: $nota->nama_customer ?: 'Pelanggan',
                    keteranganDefault: "Pelunasan Piutang Penjualan (Transfer {$rekening->namaAkun()}, split)".($dpUntukTransfer ? ' + Reklas Uang Muka Pelanggan' : ''),
                    noJurnalOverride: $noJurnal,
                );
            }

            $this->mergeBarisPiutang($noJurnal, $nota);
        });
    }

    /**
     * Gabungkan 2+ baris Piutang Usaha (1122.0) dengan noJurnal yang sama
     * menjadi 1 baris. Dipanggil setelah kitab tunai + bank sama-sama
     * menulis baris Piutang-nya sendiri-sendiri di jurnal split.
     *
     * Setiap baris akun (Kas D / Piutang K / Uang Muka D) adalah 1 row di
     * JurnalPembantuHeader itu sendiri — tidak ada model detail terpisah.
     * Baris-baris dengan `jurnal` yang sama adalah 1 grup jurnal.
     */
    private function mergeBarisPiutang(int $noJurnal, Penjualan $nota): void
    {
        $kodeAkunPiutang = '1122.0';

        $barisPiutang = JurnalPembantuHeader::query()
            ->where('jurnal', $noJurnal)
            ->where('no_akun', $kodeAkunPiutang)
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        if ($barisPiutang->count() <= 1) {
            return;
        }

        $totalNilai = $barisPiutang->sum('total_nilai');
        $barisPertama = $barisPiutang->first();

        $barisPertama->update([
            'total_nilai' => $totalNilai,
            'keterangan' => "Pengurangan piutang customer | Nota: {$nota->no_nota}",
        ]);

        $barisPiutang->skip(1)->each->delete();
    }

    /**
     * @param  float  $piutang  Nilai baris Piutang Usaha (K). Untuk kasus
     *                          non-DP = $nominal. Untuk kasus DP = $nominal
     *                          + $dpAwal (reklas Uang Muka Pelanggan
     *                          digabung jadi pengurang Piutang yang sama).
     * @param  int|null  $dpAwal  DP awal yang direklas. Kalau diisi, kitab
     *                            yang dipakai harus varian '_dp' (3 baris),
     *                            supaya ada baris Uang Muka Pelanggan (D).
     */
    private function jalankanKitab(
        string $kodeKitab,
        Penjualan $nota,
        int $userId,
        float $nominal,
        float $piutang,
        ?int $dpAwal,
        string $keteranganDefault,
    ): void {
        DB::transaction(function () use ($kodeKitab, $nota, $userId, $nominal, $piutang, $dpAwal, $keteranganDefault) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            $context = [
                'nominal_kas' => $nominal,
                'piutang_usaha' => $piutang,
            ];

            if ($dpAwal) {
                $context['dp_penjualan'] = (float) $dpAwal;
            }

            $this->engine->buatJurnalDariKitab(
                kodeKitab: $kodeKitab,
                context: $context,
                noDokumen: $nota->no_nota,
                tglTransaksi: now(),
                modulAsal: 'pelunasan_penjualan',
                jenisTransaksi: 'bk',
                userId: $userId,
                jenisPihak: 'pelanggan',
                namaPihak: $nota->nama_customer ?: 'Pelanggan',
                keteranganDefault: $keteranganDefault,
                noJurnalOverride: $noJurnal,
            );
        });
    }
}
