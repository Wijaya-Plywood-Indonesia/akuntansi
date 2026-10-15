<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data lama di sub_anak_akuns punya nilai saldo_normal & status yang
 * casing-nya campur aduk (mis. 'Debet' vs 'debet', kemungkinan dari
 * import/seed manual langsung ke DB, bukan lewat form aplikasi).
 *
 * Kolom-kolom ini adalah string bebas (tidak ada enum/check constraint di
 * DB), sementara Select field di form aplikasi cuma mengenal huruf kecil
 * ('debet', 'kredit', 'aktif', 'nonaktif'). Akibatnya waktu sub akun yang
 * nilainya kapital (mis. 'Debet') dibuka lewat form Edit, Filament tidak
 * menemukan value itu di daftar opsi -> muncul error
 * "The selected saldo Normal is invalid" walau kelihatannya sudah terisi.
 *
 * Migration ini menormalkan semua nilai lama ke huruf kecil supaya cocok
 * dengan opsi yang dikenal form, dan sekalian menyamakan 'non-aktif'
 * (dengan strip, dipakai di salah satu form lain) jadi 'nonaktif'.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sub_anak_akuns')
            ->whereNotNull('saldo_normal')
            ->update(['saldo_normal' => DB::raw('LOWER(saldo_normal)')]);

        DB::table('sub_anak_akuns')
            ->whereNotNull('status')
            ->update(['status' => DB::raw('LOWER(status)')]);

        DB::table('sub_anak_akuns')
            ->where('status', 'non-aktif')
            ->update(['status' => 'nonaktif']);

        DB::table('sub_anak_akuns')
            ->whereNotNull('tipe_buku_pembantu')
            ->update(['tipe_buku_pembantu' => DB::raw('LOWER(tipe_buku_pembantu)')]);
    }

    public function down(): void
    {
        // Normalisasi data tidak reversible (nilai kapital aslinya tidak
        // disimpan), jadi down() sengaja dikosongkan.
    }
};