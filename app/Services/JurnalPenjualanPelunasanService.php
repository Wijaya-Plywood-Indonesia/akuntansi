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
 * Metode CAMPUR (split tunai + transfer) memanggil 2 kitab sekaligus
 * dalam 1 noJurnal (kitab tunai + kitab bank), yang masing-masing
 * menulis baris Piutang Usaha-nya sendiri. Setelah itu, kedua baris
 * Piutang tersebut di-merge (lihat mergeBarisPiutang()) jadi 1 baris
 * gabungan supaya laporan jurnal tetap rapi (1 baris Piutang per
 * pelunasan, senilai total tunai + transfer).
 */
class JurnalPenjualanPelunasanService
{
    private const KODE_KITAB_TUNAI = 'pelunasan_triplek_non_ppn_tunai';

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

    public function __construct(
        private readonly BukuKitabJurnalService $engine,
    ) {}

    /**
     * Bangun jurnal pelunasan untuk pembayaran TUNAI.
     */
    public function buatJurnalPelunasanTunai(Penjualan $nota, int $userId, float $nominal): void
    {
        if ($nominal <= 0) {
            return;
        }

        $this->jalankanKitab(
            kodeKitab: self::KODE_KITAB_TUNAI,
            nota: $nota,
            userId: $userId,
            nominal: $nominal,
            keteranganDefault: 'Pelunasan Piutang Penjualan (Tunai)',
        );
    }

    /**
     * Bangun jurnal pelunasan untuk pembayaran TRANSFER via rekening tertentu.
     *
     * @throws RuntimeException Kalau rekening tidak punya akun yang
     *                          terpetakan ke kitab pelunasan bank manapun.
     */
    public function buatJurnalPelunasanTransfer(
        Penjualan $nota,
        int $userId,
        float $nominal,
        RekeningPerusahaan $rekening,
    ): void {
        if ($nominal <= 0) {
            return;
        }

        $kodeAkun = $rekening->kodeAkun();
        $kodeKitab = $kodeAkun ? (self::MAP_KITAB_BANK[$kodeAkun] ?? null) : null;

        if (! $kodeKitab) {
            throw new RuntimeException(
                "Rekening '{$rekening->atas_nama}' (akun: ".($kodeAkun ?: '-').') belum punya kitab pelunasan yang terpetakan. '.
                'Hubungi admin untuk melengkapi MAP_KITAB_BANK di JurnalPenjualanPelunasanService.'
            );
        }

        $this->jalankanKitab(
            kodeKitab: $kodeKitab,
            nota: $nota,
            userId: $userId,
            nominal: $nominal,
            keteranganDefault: "Pelunasan Piutang Penjualan (Transfer {$rekening->namaAkun()})",
        );
    }

    /**
     * Bangun jurnal pelunasan untuk pembayaran CAMPUR (split tunai + transfer).
     * Memanggil 2 kitab (tunai + bank) sekaligus dalam 1 noJurnal, lalu
     * meng-merge 2 baris Piutang Usaha hasil kedua kitab jadi 1 baris
     * gabungan (lihat mergeBarisPiutang()).
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
    ): void {
        if ($nominalTunai <= 0 && $nominalTransfer <= 0) {
            return;
        }

        $kodeAkun = $rekening->kodeAkun();
        $kodeKitabBank = $kodeAkun ? (self::MAP_KITAB_BANK[$kodeAkun] ?? null) : null;

        if ($nominalTransfer > 0 && ! $kodeKitabBank) {
            throw new RuntimeException(
                "Rekening '{$rekening->atas_nama}' (akun: ".($kodeAkun ?: '-').') belum punya kitab pelunasan yang terpetakan. '.
                'Hubungi admin untuk melengkapi MAP_KITAB_BANK di JurnalPenjualanPelunasanService.'
            );
        }

        DB::transaction(function () use ($nota, $userId, $nominalTunai, $nominalTransfer, $kodeKitabBank, $rekening) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            if ($nominalTunai > 0) {
                $this->engine->buatJurnalDariKitab(
                    kodeKitab: self::KODE_KITAB_TUNAI,
                    context: [
                        'nominal_kas' => $nominalTunai,
                        'piutang_usaha' => $nominalTunai,
                    ],
                    noDokumen: $nota->no_nota,
                    tglTransaksi: now(),
                    modulAsal: 'pelunasan_penjualan',
                    jenisTransaksi: 'bk',
                    userId: $userId,
                    jenisPihak: 'pelanggan',
                    namaPihak: $nota->nama_customer ?: 'Pelanggan',
                    keteranganDefault: 'Pelunasan Piutang Penjualan (Tunai, split)',
                    noJurnalOverride: $noJurnal,
                );
            }

            if ($nominalTransfer > 0) {
                $this->engine->buatJurnalDariKitab(
                    kodeKitab: $kodeKitabBank,
                    context: [
                        'nominal_kas' => $nominalTransfer,
                        'piutang_usaha' => $nominalTransfer,
                    ],
                    noDokumen: $nota->no_nota,
                    tglTransaksi: now(),
                    modulAsal: 'pelunasan_penjualan',
                    jenisTransaksi: 'bk',
                    userId: $userId,
                    jenisPihak: 'pelanggan',
                    namaPihak: $nota->nama_customer ?: 'Pelanggan',
                    keteranganDefault: "Pelunasan Piutang Penjualan (Transfer {$rekening->namaAkun()}, split)",
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
     * Setiap baris akun (Kas D / Piutang K) adalah 1 row di
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

    private function jalankanKitab(
        string $kodeKitab,
        Penjualan $nota,
        int $userId,
        float $nominal,
        string $keteranganDefault,
    ): void {
        DB::transaction(function () use ($kodeKitab, $nota, $userId, $nominal, $keteranganDefault) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            $this->engine->buatJurnalDariKitab(
                kodeKitab: $kodeKitab,
                context: [
                    'nominal_kas' => $nominal,
                    'piutang_usaha' => $nominal,
                ],
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
