<?php

namespace App\Support;

/**
 * Katalog resmi "variabel_nilai" — daftar label nilai yang tersedia untuk
 * dipakai di baris Buku Kitab (buku_kitab_akuns.variabel_nilai).
 *
 * CARA BACA:
 *   - Key   = nilai yang disimpan di DB & dipakai programmer sebagai kunci
 *             di $context saat generate jurnal (BukuKitabJurnalService).
 *   - Value = label yang tampil di dropdown form Buku Kitab untuk admin.
 *
 * SIAPA YANG BOLEH UBAH APA:
 *   - Menambah/mengubah daftar KEY di file ini → butuh programmer, karena
 *     tiap key harus ada logic yang menghitung nilainya di kode modul
 *     (Penjualan/Pembelian/Produksi/dst).
 *   - Memilih variabel mana dipasang ke akun/posisi mana di suatu template
 *     → 100% admin, kapan saja, lewat menu Buku Kitab, TANPA programmer.
 *
 * Lihat README_BUKU_KITAB.md untuk penjelasan konsep lengkap.
 */
class KatalogVariabelKitab
{
    public const OPSI = [

        // ── Kas / Bank (satu variabel untuk SEMUA rekening) ──────────
        // Rekening/bank mana yang dipakai sudah ditentukan lewat no_akun
        // di baris template itu sendiri, jadi variabelnya cukup 1 saja.
        'nominal_kas' => 'Nominal Kas/Bank (diterima atau dibayar)',

        // ── Uang Muka (DP) ────────────────────────────────────────────
        'dp_penjualan' => 'DP Penjualan (Pendapatan Diterima di Muka)',
        'dp_pembelian' => 'DP Pembelian (Uang Muka Pembelian)',

        // ── Penjualan ─────────────────────────────────────────────────
        'nilai_penjualan'    => 'Nilai Penjualan (Pendapatan)',
        'hpp'                => 'Harga Pokok Penjualan (HPP)',
        'ppn_keluaran'       => 'PPN Keluaran (Hutang PPN)',
        'piutang_usaha'      => 'Piutang Usaha',
        'hutang_gaji'        => 'Hutang Gaji (alokasi produksi)',
        'pendapatan_lainnya' => 'Pendapatan Usaha Lainnya',
        'piutang_lainnya'    => 'Piutang Lainnya',

        // ── Pembelian ─────────────────────────────────────────────────
        'ppn_masukan'        => 'PPN Masukan (Piutang PPN)',
        'hutang_usaha'       => 'Hutang / Utang Usaha',
        'hutang_ongkos_kayu' => 'Hutang Ongkos Turun Kayu',

        // ── Persediaan (umum) ─────────────────────────────────────────
        'persediaan_barang_jadi'    => 'Persediaan Barang Jadi',
        'persediaan_triplek_mentah' => 'Persediaan Triplek Mentah',
        'persediaan_triplek_jadi'   => 'Persediaan Triplek Jadi',
        'persediaan_triplek_gudang1'=> 'Persediaan Triplek Gudang 1',
        'persediaan_platform_mentah'=> 'Persediaan Platform Mentah',
        'persediaan_platform_jadi'  => 'Persediaan Platform Jadi',
        'persediaan_kayu_130'       => 'Persediaan Kayu 130',
        'persediaan_kayu_260'       => 'Persediaan Kayu 260',
        'persediaan_logcore_130'    => 'Persediaan Logcore 130',
        'persediaan_logcore_260'    => 'Persediaan Logcore 260',

        // ── Persediaan Veneer (produksi) ────────────────────────────────
        'persediaan_veneer_basah_fb'          => 'Persediaan Veneer Basah Face Back',
        'persediaan_veneer_basah_core'        => 'Persediaan Veneer Basah Core',
        'persediaan_veneer_kering_fb'         => 'Persediaan Veneer Kering Face Back',
        'persediaan_veneer_kering_core'       => 'Persediaan Veneer Kering Core',
        'persediaan_veneer_kering_ppc'        => 'Persediaan Veneer Kering PPC',
        'persediaan_veneer_jadi_fb'           => 'Persediaan Veneer Jadi Face Back',
        'persediaan_veneer_jadi_core'         => 'Persediaan Veneer Jadi Core',
        'persediaan_veneer_jadi_ppc'          => 'Persediaan Veneer Jadi PPC',
        'persediaan_veneer_dalam_repair'      => 'Persediaan Veneer Dalam Repair',
        'persediaan_veneer_dalam_pengeringan' => 'Persediaan Veneer Dalam Pengeringan',

        // ── Bahan Baku Pendukung Produksi ─────────────────────────────
        'bahan_lem'           => 'Bahan: Lem',
        'bahan_tepung'        => 'Bahan: Tepung',
        'bahan_pewarna'       => 'Bahan: Pewarna',
        'bahan_hardner'       => 'Bahan: Hardner',
        'bahan_solasi_putih'  => 'Bahan: Solasi Putih',
        'bahan_solasi_coklat' => 'Bahan: Solasi Coklat',
        'bahan_isi_staples'   => 'Bahan: Isi Staples',
        'perlengkapan_pabrik' => 'Perlengkapan Pabrik',
        'bop'                 => 'Biaya Operasional Pabrik (BOP)',

        // ── Produksi (selisih & lainnya) ────────────────────────────────
        'selisih_patok' => 'Selisih Harga Patok Produksi',

        // ── Serbaguna ───────────────────────────────────────────────────
        'nilai_manual' => 'Nilai Manual (diisi tetap oleh admin, bukan dari transaksi)',
    ];

    /** @return array<string,string>  key => label, untuk Select::options() */
    public static function options(): array
    {
        return self::OPSI;
    }
}