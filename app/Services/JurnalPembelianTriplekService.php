<?php

namespace App\Services;

use App\Models\BukuKitab;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\Pembelian;
use App\Models\PembelianMetodePembayaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Jurnal Pembelian — berbasis template Buku Kitab (BukuKitabJurnalService),
 * menggantikan JurnalPembelianService lama yang hardcode.
 *
 * Mendukung jenis_pembayaran NORMAL, BAYAR_DIMUKA, dan DP.
 *
 *   - NORMAL       : barang & Hutang Usaha PENUH diakui SEKARANG (kode
 *                    'pembelian_bayar_dibelakang_bml'), TIDAK peduli berapa
 *                    yang dibayar bersamaan. Kalau ADA pembayaran bersamaan
 *                    (tercatat di $pembelian->metodePembayarans), itu di-
 *                    posting sebagai pelunasan INSTAN terpisah (kode
 *                    'pembelian_bayar_dibelakang_lunas_*') dengan nomor
 *                    jurnal yang SAMA, supaya tetap 1 grup.
 *   - BAYAR_DIMUKA : uang dibayar PENUH duluan (kode
 *                    'pembelian_bayar_dimuka_lunas_*'). Barang BELUM diakui
 *                    di sini — itu nanti di menu "Kedatangan Barang" lewat
 *                    buatJurnalKedatanganBarangDimuka().
 *   - DP           : sama seperti BAYAR_DIMUKA tapi uang boleh dibayar
 *                    BERTAHAP (kode 'pembelian_down_payment_pembayaran_*'
 *                    untuk tiap tahap DP, lewat method ini untuk tahap
 *                    pertama & buatJurnalTambahDp() untuk tahap susulan).
 *                    Barang & pelunasan SISA diakui bersamaan saat barang
 *                    datang lewat buatJurnalKedatanganBarangDp() — kitab
 *                    'pembelian_down_payment_barang_datang_*' TIDAK punya
 *                    baris Utang Usaha, jadi wajib lunas pas titik itu.
 *
 * Akun Persediaan diambil DINAMIS per barang (dari Barang::subAnakAkun
 * masing-masing) — BUKAN dari 1 akun tetap di template — karena barang yang
 * dibeli bisa macam-macam dengan akun persediaan berbeda-beda (beda dari
 * kasus Penjualan Triplek yang semuanya nebeng 1 akun sama). Lihat
 * BukuKitabJurnalService::buatJurnalDariKitab() param $itemBreakdown yang
 * sekarang mendukung override no_akun/nama_akun per baris.
 */
class JurnalPembelianTriplekService
{
    private const KODE_PERSEDIAAN_FALLBACK = '1402.0';

    public function __construct(
        private readonly BukuKitabJurnalService $engine,
    ) {}

    public function buatJurnalPembelian(Pembelian $pembelian, int $userId): void
    {
        $pembelian->loadMissing([
            'detailPembelians.barang.subAnakAkun',
            'metodePembayarans.rekeningPerusahaan.subAnakAkun',
        ]);

        $breakdownPersediaan = $this->siapkanBreakdownPersediaan($pembelian);
        $nilaiPersediaanTotal = $this->hitungTotalBreakdown($breakdownPersediaan);
        $ppnMasukan = (float) $pembelian->total_ppn;
        $hutangUsahaPenuh = (float) $pembelian->grand_total;

        DB::transaction(function () use ($pembelian, $userId, $breakdownPersediaan, $nilaiPersediaanTotal, $ppnMasukan, $hutangUsahaPenuh) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            if ($pembelian->jenis_pembayaran === \App\Models\Pembelian::JENIS_BAYAR_DIMUKA) {
                // ── BAYAR_DIMUKA: cuma catat DP keluar, barang BELUM diakui ──
                $this->postingUangMukaGabungan(
                    $pembelian,
                    $pembelian->metodePembayarans,
                    $userId,
                    $noJurnal,
                    'pembelian_bayar_dimuka_lunas',
                    'Pembayaran DP Pembelian',
                );

                return;
            }

            if ($pembelian->jenis_pembayaran === \App\Models\Pembelian::JENIS_DP) {
                // ── DP: catat SEMUA setoran DP tahap pertama yang tercatat
                //    bersamaan saat nota dibuat. Barang & Hutang Usaha BELUM
                //    diakui sama sekali di sini (tidak ada baris itu di kitab
                //    'pembelian_down_payment_pembayaran_*'). Setoran DP
                //    SUSULAN (setelah nota ini divalidasi) TIDAK lewat sini,
                //    tapi lewat buatJurnalTambahDp().
                $this->postingUangMukaGabungan(
                    $pembelian,
                    $pembelian->metodePembayarans,
                    $userId,
                    $noJurnal,
                    'pembelian_down_payment_pembayaran',
                    'Pembayaran DP Pembelian (Tahap 1)',
                );

                return;
            }

            // ── NORMAL: barang & Hutang Usaha PENUH diakui sekarang ──────
            $this->engine->buatJurnalDariKitab(
                kodeKitab: 'pembelian_bayar_dibelakang_bml',
                context: [
                    'nilai_persediaan_dinamis' => $nilaiPersediaanTotal,
                    'ppn_masukan'  => $ppnMasukan,
                    'hutang_usaha' => $hutangUsahaPenuh,
                ],
                noDokumen: $pembelian->nomor_nota,
                tglTransaksi: $pembelian->tanggal,
                modulAsal: 'pembelian_barang',
                jenisTransaksi: 'bm',
                userId: $userId,
                jenisPihak: 'supplier',
                namaPihak: $pembelian->supplier_name ?: 'Supplier',
                keteranganDefault: 'Pembelian Barang',
                itemBreakdown: [
                    'nilai_persediaan_dinamis' => $breakdownPersediaan,
                ],
                splitHeaderPerBarang: ['nilai_persediaan_dinamis'],
                noJurnalOverride: $noJurnal,
            );

            // ── Kalau ada pembayaran bersamaan, langsung posting pelunasan
            //    instan (nomor jurnal SAMA) — bukan bagian dari template di
            //    atas, supaya Hutang Usaha di atas tetap "penuh" apa adanya.
            foreach ($pembelian->metodePembayarans as $bayar) {
                if ((float) $bayar->amount <= 0) {
                    continue;
                }

                $kodeKitab = $this->resolveKodePembayaran($bayar, 'pembelian_bayar_dibelakang_lunas');

                $this->engine->buatJurnalDariKitab(
                    kodeKitab: $kodeKitab,
                    context: [
                        'hutang_usaha' => (float) $bayar->amount,
                        'nominal_kas'  => (float) $bayar->amount,
                    ],
                    noDokumen: $pembelian->nomor_nota,
                    tglTransaksi: $pembelian->tanggal,
                    modulAsal: 'pembelian_barang',
                    jenisTransaksi: 'bm',
                    userId: $userId,
                    jenisPihak: 'supplier',
                    namaPihak: $pembelian->supplier_name ?: 'Supplier',
                    keteranganDefault: 'Pelunasan Hutang (Langsung Saat Pembelian)',
                    noJurnalOverride: $noJurnal,
                    catatanPerVariabel: [
                        'nominal_kas' => $this->gabungCatatan($pembelian, $bayar),
                    ],
                );
            }
        });
    }

    /**
     * TAHAP "KEDATANGAN BARANG" untuk BAYAR_DIMUKA — belum dipanggil dari UI
     * manapun (menu-nya belum dibuat), disiapkan lebih dulu supaya siap
     * pakai. Mengakui Persediaan + PPN Masukan, dan menghabiskan Uang Muka
     * yang sudah dibayar sebelumnya.
     */
    public function buatJurnalKedatanganBarangDimuka(Pembelian $pembelian, int $userId, ?string $tanggal = null): void
    {
        $pembelian->loadMissing(['detailPembelians.barang.subAnakAkun', 'metodePembayarans']);

        $breakdownPersediaan = $this->siapkanBreakdownPersediaan($pembelian);
        $nilaiPersediaanTotal = $this->hitungTotalBreakdown($breakdownPersediaan);
        $ppnMasukan = (float) $pembelian->total_ppn;
        $dpSudahDibayar = (float) $pembelian->metodePembayarans->sum('amount');

        DB::transaction(function () use ($pembelian, $userId, $breakdownPersediaan, $nilaiPersediaanTotal, $ppnMasukan, $dpSudahDibayar, $tanggal) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            $this->engine->buatJurnalDariKitab(
                kodeKitab: 'pembelian_bayar_dimuka_bml',
                context: [
                    'nilai_persediaan_dinamis' => $nilaiPersediaanTotal,
                    'ppn_masukan'  => $ppnMasukan,
                    'dp_pembelian' => $dpSudahDibayar,
                ],
                noDokumen: $pembelian->nomor_nota,
                tglTransaksi: $tanggal ?: $pembelian->tanggal,
                modulAsal: 'pembelian_barang',
                jenisTransaksi: 'bm',
                userId: $userId,
                jenisPihak: 'supplier',
                namaPihak: $pembelian->supplier_name ?: 'Supplier',
                keteranganDefault: 'Kedatangan Barang (Bayar Dimuka)',
                itemBreakdown: [
                    'nilai_persediaan_dinamis' => $breakdownPersediaan,
                ],
                splitHeaderPerBarang: ['nilai_persediaan_dinamis'],
                noJurnalOverride: $noJurnal,
            );
        });
    }

    /**
     * DP tahap SUSULAN (setelah nota jenis DP sudah divalidasi) — dipanggil
     * dari menu "Kedatangan Barang" -> aksi "Tambah DP". Mencatat 1 setoran
     * DP baru (D: Uang Muka Pembelian, K: Kas/Bank) via kitab
     * 'pembelian_down_payment_pembayaran_*'. Boleh dipanggil berkali-kali
     * selama barang belum datang.
     */
    public function buatJurnalTambahDp(Pembelian $pembelian, PembelianMetodePembayaran $bayar, int $userId, ?string $tanggal = null): void
    {
        if ((float) $bayar->amount <= 0) {
            return;
        }

        $bayar->loadMissing('rekeningPerusahaan.subAnakAkun');

        DB::transaction(function () use ($pembelian, $bayar, $userId, $tanggal) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            $this->postingUangMukaGabungan(
                $pembelian,
                collect([$bayar]),
                $userId,
                $noJurnal,
                'pembelian_down_payment_pembayaran',
                'Pembayaran DP Pembelian (Tahap Lanjutan)',
                $tanggal,
            );
        });
    }

    /**
     * TAHAP "KEDATANGAN BARANG" untuk DP — kasus barang datang DULUAN,
     * sisa tagihan BELUM dibayar sama sekali di titik ini (akan dicicil
     * belakangan lewat buatJurnalBayarHutangDp()). Dipanggil dari menu
     * "Kedatangan Barang" -> aksi "Konfirmasi Barang Datang" ketika nominal
     * pelunasan yang diisi user TIDAK menutup penuh sisa tagihan.
     *
     * Beda dari buatJurnalKedatanganBarangDp(): di sini Uang Muka Pembelian
     * yang sudah terkumpul TIDAK dibalik/disentuh sama sekali — Utang Usaha
     * diakui PENUH sebesar grand_total (kitab
     * 'pembelian_down_payment_barang_datang_belum_lunas' tidak punya baris
     * 'dp_pembelian'). Baru nanti di cicilan TERAKHIR
     * (buatJurnalBayarHutangDp()), Uang Muka Pembelian ini dibalik sekaligus
     * untuk menutup Utang Usaha ke 0.
     */
    public function buatJurnalKedatanganBarangDpBelumLunas(Pembelian $pembelian, int $userId, ?string $tanggal = null): void
    {
        $pembelian->loadMissing(['detailPembelians.barang.subAnakAkun']);

        $breakdownPersediaan = $this->siapkanBreakdownPersediaan($pembelian);
        $nilaiPersediaanTotal = $this->hitungTotalBreakdown($breakdownPersediaan);
        $ppnMasukan = (float) $pembelian->total_ppn;
        $hutangUsahaPenuh = (float) $pembelian->grand_total;

        DB::transaction(function () use ($pembelian, $userId, $breakdownPersediaan, $nilaiPersediaanTotal, $ppnMasukan, $hutangUsahaPenuh, $tanggal) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            $this->engine->buatJurnalDariKitab(
                kodeKitab: 'pembelian_down_payment_barang_datang_belum_lunas',
                context: [
                    'nilai_persediaan_dinamis' => $nilaiPersediaanTotal,
                    'ppn_masukan'  => $ppnMasukan,
                    'hutang_usaha' => $hutangUsahaPenuh,
                ],
                noDokumen: $pembelian->nomor_nota,
                tglTransaksi: $tanggal ?: $pembelian->tanggal,
                modulAsal: 'pembelian_barang',
                jenisTransaksi: 'bm',
                userId: $userId,
                jenisPihak: 'supplier',
                namaPihak: $pembelian->supplier_name ?: 'Supplier',
                keteranganDefault: 'Kedatangan Barang (DP, Belum Lunas — Sisa Dicicil Belakangan)',
                itemBreakdown: [
                    'nilai_persediaan_dinamis' => $breakdownPersediaan,
                ],
                splitHeaderPerBarang: ['nilai_persediaan_dinamis'],
                noJurnalOverride: $noJurnal,
            );
        });
    }

    /**
     * Pelunasan hutang untuk Pembelian jenis DP yang barangnya SUDAH datang
     * (via buatJurnalKedatanganBarangDpBelumLunas()) tapi masih ada sisa
     * tagihan — dipanggil dari menu "Kedatangan Barang" -> aksi "Bayar
     * Hutang" khusus nota DP yang sudah menerima barang. Boleh dicicil
     * berkali-kali seperti buatJurnalBayarHutang() punya NORMAL, TAPI di
     * cicilan TERAKHIR (begitu sisaTagihan() jadi 0 setelah baris
     * pembayaran $bayar ini disimpan) turut membalik SELURUH Uang Muka
     * Pembelian yang sudah terkumpul ($pembelian->
     * dp_terkumpul_saat_barang_datang) supaya Utang Usaha benar-benar
     * tertutup ke 0 — bukan cuma tertutup sebesar kas yang dibayar.
     *
     * $bayar WAJIB sudah ter-save ke DB sebelum method ini dipanggil, supaya
     * $pembelian->sisaTagihan() (query fresh ke metodePembayarans) sudah
     * memperhitungkan pembayaran ini saat menentukan "apakah ini cicilan
     * terakhir".
     */
    public function buatJurnalBayarHutangDp(Pembelian $pembelian, PembelianMetodePembayaran $bayar, int $userId, ?string $tanggal = null): void
    {
        if ((float) $bayar->amount <= 0) {
            return;
        }

        $bayar->loadMissing('rekeningPerusahaan.subAnakAkun');

        DB::transaction(function () use ($pembelian, $bayar, $userId, $tanggal) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            $sisaSetelahBayar = $pembelian->sisaTagihan();
            $iniCicilanTerakhir = $sisaSetelahBayar <= 0.0001;
            $dpNetting = $iniCicilanTerakhir ? (float) ($pembelian->dp_terkumpul_saat_barang_datang ?? 0) : 0.0;
            $nominalBayar = (float) $bayar->amount;

            $kodeKitab = $this->resolveKodePembayaran($bayar, 'pembelian_down_payment_pelunasan');

            $this->engine->buatJurnalDariKitab(
                kodeKitab: $kodeKitab,
                context: [
                    // D: Utang Usaha wajib = jumlah SEMUA sisi K (kas + DP
                    // yang dibalik), supaya jurnal tetap balance.
                    'hutang_usaha' => $nominalBayar + $dpNetting,
                    'nominal_kas'  => $nominalBayar,
                    'dp_pembelian' => $dpNetting, // auto-skip kalau 0 (bukan cicilan terakhir)
                ],
                noDokumen: $pembelian->nomor_nota,
                tglTransaksi: $tanggal ?: now(),
                modulAsal: 'pembelian_barang',
                jenisTransaksi: 'bm',
                userId: $userId,
                jenisPihak: 'supplier',
                namaPihak: $pembelian->supplier_name ?: 'Supplier',
                keteranganDefault: $iniCicilanTerakhir
                    ? 'Pelunasan Hutang Pembelian (DP, Cicilan Terakhir — Tutup Uang Muka)'
                    : 'Pelunasan Hutang Pembelian (DP, Cicilan)',
                noJurnalOverride: $noJurnal,
                catatanPerVariabel: [
                    'nominal_kas' => $this->gabungCatatan($pembelian, $bayar),
                ],
            );
        });
    }

    /**
     * TAHAP "KEDATANGAN BARANG" untuk DP — dipanggil dari menu "Kedatangan
     * Barang" -> aksi "Konfirmasi Barang Datang" khusus nota jenis DP.
     *
     * Mengakui Persediaan + PPN Masukan, menghabiskan SELURUH Uang Muka DP
     * yang sudah terkumpul ($dpSudahDibayar — TIDAK termasuk pelunasan sisa
     * yang dibayar bersamaan di titik ini), dan mencatat pelunasan sisa lewat
     * Kas/Bank ($bayarSisa). Kitab 'pembelian_down_payment_barang_datang_*'
     * TIDAK punya baris Utang Usaha — jadi $bayarSisa WAJIB pas menutup sisa
     * tagihan (divalidasi di PembelianKedatanganService, bukan di sini).
     */
    public function buatJurnalKedatanganBarangDp(
        Pembelian $pembelian,
        float $dpSudahDibayar,
        PembelianMetodePembayaran $bayarSisa,
        int $userId,
        ?string $tanggal = null,
    ): void {
        $pembelian->loadMissing(['detailPembelians.barang.subAnakAkun']);
        $bayarSisa->loadMissing('rekeningPerusahaan.subAnakAkun');

        $breakdownPersediaan = $this->siapkanBreakdownPersediaan($pembelian);
        $nilaiPersediaanTotal = $this->hitungTotalBreakdown($breakdownPersediaan);
        $ppnMasukan = (float) $pembelian->total_ppn;
        $nominalSisa = (float) $bayarSisa->amount;

        DB::transaction(function () use ($pembelian, $userId, $breakdownPersediaan, $nilaiPersediaanTotal, $ppnMasukan, $dpSudahDibayar, $bayarSisa, $nominalSisa, $tanggal) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            // Kalau sisa = 0 (DP sebelumnya sudah menutup 100% grand_total),
            // baris nominal_kas otomatis dilewati oleh engine (nilai <= 0),
            // jadi kode kitab yang dipakai tidak berpengaruh ke hasil —
            // tetap resolve by payment_method supaya konsisten.
            $kodeKitab = $this->resolveKodePembayaran($bayarSisa, 'pembelian_down_payment_barang_datang');

            $this->engine->buatJurnalDariKitab(
                kodeKitab: $kodeKitab,
                context: [
                    'nilai_persediaan_dinamis' => $nilaiPersediaanTotal,
                    'ppn_masukan'  => $ppnMasukan,
                    'dp_pembelian' => $dpSudahDibayar,
                    'nominal_kas'  => $nominalSisa,
                ],
                noDokumen: $pembelian->nomor_nota,
                tglTransaksi: $tanggal ?: $pembelian->tanggal,
                modulAsal: 'pembelian_barang',
                jenisTransaksi: 'bm',
                userId: $userId,
                jenisPihak: 'supplier',
                namaPihak: $pembelian->supplier_name ?: 'Supplier',
                keteranganDefault: 'Kedatangan Barang & Pelunasan Sisa (DP)',
                itemBreakdown: [
                    'nilai_persediaan_dinamis' => $breakdownPersediaan,
                ],
                splitHeaderPerBarang: ['nilai_persediaan_dinamis'],
                noJurnalOverride: $noJurnal,
                catatanPerVariabel: [
                    'nominal_kas' => $this->gabungCatatan($pembelian, $bayarSisa),
                ],
            );
        });
    }

    /**
     * Pelunasan hutang untuk Pembelian jenis NORMAL yang terjadi BELAKANGAN
     * (bukan bersamaan saat input transaksi) — dipanggil dari menu
     * "Kedatangan Barang" -> aksi "Bayar Hutang". Barang & Utang Usaha PENUH
     * sudah diakui sejak validasi awal (lihat buatJurnalPembelian()), jadi
     * di sini cuma jurnal pelunasan: D: Utang Usaha | K: Kas/Bank. Boleh
     * dipanggil berkali-kali (cicilan) sampai sisaTagihan() = 0.
     */
    public function buatJurnalBayarHutang(Pembelian $pembelian, PembelianMetodePembayaran $bayar, int $userId, ?string $tanggal = null): void
    {
        if ((float) $bayar->amount <= 0) {
            return;
        }

        $bayar->loadMissing('rekeningPerusahaan.subAnakAkun');

        DB::transaction(function () use ($pembelian, $bayar, $userId, $tanggal) {
            $noJurnal = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;

            $kodeKitab = $this->resolveKodePembayaran($bayar, 'pembelian_bayar_dibelakang_lunas');

            $this->engine->buatJurnalDariKitab(
                kodeKitab: $kodeKitab,
                context: [
                    'hutang_usaha' => (float) $bayar->amount,
                    'nominal_kas'  => (float) $bayar->amount,
                ],
                noDokumen: $pembelian->nomor_nota,
                tglTransaksi: $tanggal ?: now(),
                modulAsal: 'pembelian_barang',
                jenisTransaksi: 'bm',
                userId: $userId,
                jenisPihak: 'supplier',
                namaPihak: $pembelian->supplier_name ?: 'Supplier',
                keteranganDefault: 'Pelunasan Hutang Pembelian (Jatuh Tempo)',
                noJurnalOverride: $noJurnal,
                catatanPerVariabel: [
                    'nominal_kas' => $this->gabungCatatan($pembelian, $bayar),
                ],
            );
        });
    }

    /**
     * FIX BUG: kalau pembayaran DP/Bayar Dimuka di-split Tunai & Transfer
     * (2 baris PembelianMetodePembayaran), memanggil buatJurnalDariKitab()
     * SEKALI PER BARIS (seperti sebelumnya) membuat baris "Uang Muka
     * Pembelian" ikut TERDUPLIKASI 2x — padahal harusnya digabung jadi 1
     * baris (total), dan yang dipecah cuma sisi Kas/Bank-nya saja.
     *
     * Method ini posting manual (bukan lewat 1 kitab utuh sekali panggil):
     * - 1 header D: Uang Muka Pembelian = TOTAL semua $pembayarans
     * - N header K: Kas/Bank, masing-masing = amount per baris pembayaran
     *
     * Kode akun tetap diambil dari template Buku Kitab yang sudah ada
     * (bukan hardcode) — cuma cara motongnya jadi manual per-baris, bukan
     * per-template.
     *
     * @param  \Illuminate\Support\Collection<int, PembelianMetodePembayaran>  $pembayarans
     */
    private function postingUangMukaGabungan(
        Pembelian $pembelian,
        $pembayarans,
        int $userId,
        int $noJurnal,
        string $prefixKitab,
        string $keteranganDefault,
        ?string $tanggal = null,
    ): void {
        $pembayaranValid = $pembayarans->filter(fn ($b) => (float) $b->amount > 0)->values();

        if ($pembayaranValid->isEmpty()) {
            return;
        }

        $totalUangMuka = (float) $pembayaranValid->sum('amount');
        $nota = $pembelian->nomor_nota;
        $supplier = $pembelian->supplier_name ?: 'Supplier';

        // Ambil baris D (Uang Muka Pembelian) dari kitab pembayaran PERTAMA
        // sebagai representatif — semua varian bank/tunai dalam 1 grup
        // prefix ini SAMA akun Uang Muka-nya (cuma beda di sisi K).
        $kodeKitabPertama = $this->resolveKodePembayaran($pembayaranValid->first(), $prefixKitab);
        $barisUangMuka = BukuKitab::templateAkun($kodeKitabPertama)
            ->firstWhere('variabel_nilai', 'dp_pembelian');

        if (! $barisUangMuka) {
            throw new RuntimeException(
                "Baris 'dp_pembelian' (Uang Muka Pembelian) tidak ditemukan di kitab '{$kodeKitabPertama}'."
            );
        }

        // Tanggal transaksi jurnal ini HARUS ikut tanggal pembayaran yang
        // sesungguhnya (mis. DP tahap lanjutan yang dibayar beberapa hari
        // setelah nota dibuat), bukan selalu tanggal nota asli — supaya
        // Rekap Arus Kas & Jurnal Umum mencerminkan kapan uang benar-benar
        // keluar. Kalau tidak dikirim (dipanggil dari alur lama), fallback
        // ke tanggal nota seperti sebelumnya.
        $tglTransaksi = $tanggal ?: $pembelian->tanggal;

        // ── D: Uang Muka Pembelian (1 baris, TOTAL gabungan) ────────────
        $headerD = JurnalPembantuHeader::create([
            'no_jurnal_pembantu' => JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') + 1,
            'tgl_transaksi'      => $tglTransaksi,
            'jenis_transaksi'    => 'bm',
            'modul_asal'         => 'pembelian_barang',
            'jurnal'             => $noJurnal,
            'no_akun'            => $barisUangMuka->no_akun,
            'nama_akun'          => $barisUangMuka->nama_akun,
            'map'                => 'd',
            'keterangan'         => "{$keteranganDefault} | Nota: {$nota}" . $this->suffixCatatan(trim((string) ($pembelian->catatan ?? ''))),
            'no_dokumen'         => $nota,
            'total_nilai'        => $totalUangMuka,
            'status'             => JurnalPembantuHeader::STATUS_DRAFT,
            'dibuat_oleh'        => $userId,
        ]);

        JurnalPembantuItem::create([
            'jurnal_pembantu_header_id' => $headerD->id,
            'urut'         => 1,
            'jenis_pihak'  => 'supplier',
            'nama_pihak'   => $supplier,
            'no_dokumen'   => $nota,
            'keterangan'   => $keteranganDefault,
            'banyak'       => 1,
            'm3'           => 0,
            'harga'        => $totalUangMuka,
            'hit_kbk'      => 'b',
            'status'       => true,
            'created_by'   => $userId,
        ]);

        // ── K: Kas/Bank, dipecah per baris pembayaran ────────────────────
        foreach ($pembayaranValid as $bayar) {
            $kodeKitab = $this->resolveKodePembayaran($bayar, $prefixKitab);
            $barisKas = BukuKitab::templateAkun($kodeKitab)->firstWhere('variabel_nilai', 'nominal_kas');

            if (! $barisKas) {
                throw new RuntimeException(
                    "Baris 'nominal_kas' (Kas/Bank) tidak ditemukan di kitab '{$kodeKitab}'."
                );
            }

            $headerK = JurnalPembantuHeader::create([
                'no_jurnal_pembantu' => JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') + 1,
                'tgl_transaksi'      => $tglTransaksi,
                'jenis_transaksi'    => 'bm',
                'modul_asal'         => 'pembelian_barang',
                'jurnal'             => $noJurnal,
                'no_akun'            => $barisKas->no_akun,
                'nama_akun'          => $barisKas->nama_akun,
                'map'                => 'k',
                'keterangan'         => "{$barisKas->keterangan} | Nota: {$nota}" . $this->suffixCatatan($this->gabungCatatan($pembelian, $bayar)),
                'no_dokumen'         => $nota,
                'total_nilai'        => (float) $bayar->amount,
                'status'             => JurnalPembantuHeader::STATUS_DRAFT,
                'dibuat_oleh'        => $userId,
            ]);

            JurnalPembantuItem::create([
                'jurnal_pembantu_header_id' => $headerK->id,
                'urut'         => 1,
                'jenis_pihak'  => 'supplier',
                'nama_pihak'   => $supplier,
                'no_dokumen'   => $nota,
                'keterangan'   => $barisKas->keterangan ?: $keteranganDefault,
                'banyak'       => 1,
                'm3'           => 0,
                'harga'        => (float) $bayar->amount,
                'hit_kbk'      => 'b',
                'status'       => true,
                'created_by'   => $userId,
            ]);
        }
    }

    /* =====================================================================
     * INTERNAL
     * ===================================================================== */

    /**
     * FIX BUG: engine (BukuKitabJurnalService) men-skip suatu baris kitab
     * kalau $context[variabel_nilai] <= 0 — SEBELUM sempat melihat isi
     * $itemBreakdown sama sekali. Jadi walau breakdown per-barang sudah
     * lengkap, baris Persediaan tetap ke-skip (hilang dari jurnal) kalau
     * context-nya tidak diisi apa-apa. Total di sini WAJIB dikirim juga di
     * $context (bukan cuma itemBreakdown) supaya baris itu lolos pengecekan
     * nominal > 0 tadi.
     */
    /**
     * Gabungkan catatan dari baris pembayaran (PembelianMetodePembayaran)
     * dan catatan pembelian itu sendiri (Pembelian::catatan) supaya apapun
     * kolom yang diisi user, tetap muncul di keterangan baris kas jurnal.
     * Kalau dua-duanya diisi & berbeda, tampilkan keduanya.
     */
    private function gabungCatatan(Pembelian $pembelian, ?PembelianMetodePembayaran $bayar): string
    {
        $catatanPembayaran = trim((string) ($bayar?->catatan ?? ''));
        $catatanPembelian  = trim((string) ($pembelian->catatan ?? ''));

        if ($catatanPembayaran !== '' && $catatanPembelian !== '' && $catatanPembayaran !== $catatanPembelian) {
            return "{$catatanPembayaran} - {$catatanPembelian}";
        }

        return $catatanPembayaran !== '' ? $catatanPembayaran : $catatanPembelian;
    }

    private function suffixCatatan(string $catatan): string
    {
        return $catatan !== '' ? " ({$catatan})" : '';
    }

    private function hitungTotalBreakdown(array $breakdown): float
    {
        return round(collect($breakdown)->sum(
            fn (array $b) => (float) ($b['banyak'] ?? 0) * (float) ($b['harga'] ?? 0)
        ), 4);
    }

    private function siapkanBreakdownPersediaan(Pembelian $pembelian): array
    {
        $breakdown = [];

        foreach ($pembelian->detailPembelians as $detail) {
            $pengali = $detail->hitung_dari === 'm3'
                ? (float) $detail->kubikasi
                : (float) $detail->qty;

            if ($pengali <= 0) {
                continue;
            }

            $hargaBersih = round((float) $detail->subtotal / $pengali, 4);

            $kodeAkun = $detail->barang?->subAnakAkun?->kode_sub_anak_akun;
            $namaAkun = $detail->barang?->subAnakAkun?->nama_sub_anak_akun;

            if (! $kodeAkun) {
                Log::warning("[JurnalPembelianTriplek] Barang '{$detail->nama_barang}' belum di-set akun persediaannya. Menggunakan fallback '".self::KODE_PERSEDIAAN_FALLBACK."'.");
                $kodeAkun = self::KODE_PERSEDIAAN_FALLBACK;
                $namaAkun = '⚠ Akun persediaan belum di-set: '.$detail->nama_barang;
            }

            $breakdown[] = [
                'no_akun'     => $kodeAkun,
                'nama_akun'   => $namaAkun,
                'id_barang'   => $detail->barang_id,
                'nama_barang' => $detail->nama_barang,
                'banyak'      => $pengali,
                'harga'       => $hargaBersih,
                'keterangan'  => 'Masuk stok '.$detail->nama_barang,
            ];
        }

        return $breakdown;
    }

    private function resolveKodePembayaran(PembelianMetodePembayaran $bayar, string $prefix): string
    {
        if ($bayar->payment_method === PembelianMetodePembayaran::METODE_TRANSFER) {
            return $prefix.'_'.$this->slugBank($bayar);
        }

        return $prefix.'_tunai';
    }

    private function slugBank(PembelianMetodePembayaran $bayar): string
    {
        $namaAkun = $bayar->rekeningPerusahaan?->nama_akun
            ?? $bayar->rekeningPerusahaan?->subAnakAkun?->nama_sub_anak_akun;

        if (blank($namaAkun)) {
            throw new \RuntimeException(
                "Rekening bank untuk pembayaran pembelian (ID {$bayar->id}) tidak ditemukan — ".
                'tidak bisa menentukan kode Buku Kitab yang sesuai.'
            );
        }

        return strtolower(str_replace(' ', '_', trim($namaAkun)));
    }
}