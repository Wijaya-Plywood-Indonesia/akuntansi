<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan SEMUA nomor nota lama yang pernah dipakai pembelian ini
     * (bukan cuma nilai terakhir), supaya fitur "Sinkronkan No. Nota ke
     * Jurnal" tahu persis teks apa saja yang harus dicari & diganti di
     * jurnal_pembantu_headers & jurnal_umum — tanpa perlu menebak-nebak
     * berdasarkan kata generik seperti "sementara" yang bisa saja dipakai
     * banyak pembelian lain sebagai placeholder juga.
     */
    public function up(): void
    {
        Schema::table('pembelians', function (Blueprint $table) {
            $table->json('nomor_nota_history')->nullable()->after('nomor_nota');
        });
    }

    public function down(): void
    {
        Schema::table('pembelians', function (Blueprint $table) {
            $table->dropColumn('nomor_nota_history');
        });
    }
};