<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom id_pembeli (nullable) di jurnal_umums supaya baris
     * jurnal piutang bisa dikaitkan ke pembeli tertentu (master data
     * `pembelis`), mirip pola id_barang untuk kasus persediaan.
     *
     * Data lama (jurnal sebelum kolom ini ada) sengaja dibiarkan NULL —
     * buku pembantu piutang untuk data lama tetap fallback ke kolom teks
     * `nama` (lihat SubAnakAkun::getPiutangPembeliListAttribute() /
     * TreeAkunPage::getPiutangPembeliListProperty()).
     */
    public function up(): void
    {
        Schema::table('jurnal_umums', function (Blueprint $table) {
            $table->foreignId('id_pembeli')
                ->nullable()
                ->after('id_barang')
                ->constrained('pembelis')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jurnal_umums', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_pembeli');
        });
    }
};