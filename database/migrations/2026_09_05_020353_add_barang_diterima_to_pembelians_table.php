<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembelians', function (Blueprint $table) {
            // Null = barang belum datang (masih uang muka). Terisi = jurnal
            // kedatangan sudah diposting via JurnalPembelianTriplekService::
            // buatJurnalKedatanganBarangDimuka(), lihat menu "Kedatangan Barang".
            // Kolom 'status' (draft/hutang/cicilan/lunas/batal) TIDAK dipakai
            // untuk ini karena levelnya beda: 'status' soal pembayaran,
            // sedangkan ini soal fisik barangnya sudah di tangan atau belum.
            $table->timestamp('barang_diterima_at')->nullable()->after('jenis_pembayaran');
            $table->foreignId('barang_diterima_by')->nullable()->after('barang_diterima_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pembelians', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barang_diterima_by');
            $table->dropColumn('barang_diterima_at');
        });
    }
};