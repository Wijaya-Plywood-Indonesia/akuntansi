<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembelians', function (Blueprint $table) {
            // NORMAL | BAYAR_DIMUKA | DP — beda dari kolom 'status' yang sudah
            // ada (draft/hutang/cicilan/lunas/batal, itu status pembayarannya).
            // Ini soal SKEMA transaksinya: kapan barang datang vs kapan uang
            // keluar. DP belum diimplementasi penuh (menyusul).
            $table->string('jenis_pembayaran', 20)
                ->default('NORMAL')
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pembelians', function (Blueprint $table) {
            $table->dropColumn('jenis_pembayaran');
        });
    }
};