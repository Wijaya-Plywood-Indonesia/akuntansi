<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom id_barang (nullable) di jurnal_umums supaya baris
     * jurnal bisa dikaitkan ke barang tertentu, terutama untuk kasus di mana
     * satu akun persediaan dipakai oleh banyak barang yang berbeda.
     *
     * Data lama (jurnal sebelum kolom ini ada) sengaja dibiarkan NULL —
     * perhitungan stok untuk data lama tetap fallback ke agregat per akun
     * (lihat Barang::getStokBukuBesarAttribute()).
     */
    public function up(): void
    {
        Schema::table('jurnal_umums', function (Blueprint $table) {
            $table->foreignId('id_barang')
                ->nullable()
                ->after('no_akun')
                ->constrained('barangs')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jurnal_umums', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_barang');
        });
    }
};