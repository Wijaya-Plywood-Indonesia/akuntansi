<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            // COD | BAYAR_DIMUKA | DP
            $table->string('jenis_transaksi')->default('BAYAR_DIMUKA')->after('metode_pembayaran');
        });
    }

    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropColumn('jenis_transaksi');
        });
    }
};