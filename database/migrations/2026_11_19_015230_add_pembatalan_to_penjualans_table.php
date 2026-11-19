<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan pembatalan nota penjualan. Status nota dibatalkan memakai
 * penjualans.status_transaksi = 'DIBATALKAN' (sudah dikenal Leaderboard & tabel Penjualan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->unsignedBigInteger('dibatalkan_oleh')->nullable();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->text('alasan_batal')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropColumn(['dibatalkan_oleh', 'dibatalkan_pada', 'alasan_batal']);
        });
    }
};