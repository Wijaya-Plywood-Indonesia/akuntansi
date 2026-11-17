<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom MM / keterangan ukuran (teks bebas, mis. "18" atau "4 dim") untuk
 * penjualan: POS -> penjualan_details -> jurnal_pembantu_items -> jurnal_umums.mm
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penjualan_details', function (Blueprint $table) {
            $table->string('mm', 150)->nullable()->after('hitung_dari');
        });

        Schema::table('jurnal_pembantu_items', function (Blueprint $table) {
            $table->string('mm', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('penjualan_details', function (Blueprint $table) {
            $table->dropColumn('mm');
        });

        Schema::table('jurnal_pembantu_items', function (Blueprint $table) {
            $table->dropColumn('mm');
        });
    }
};