<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom pembeli_id (nullable) di penjualans. Kolom ini
     * SEBENARNYA sudah dikirim oleh PosPenjualan::simpanTransaksi() sejak
     * lama (lihat Penjualan::create([... 'pembeli_id' => $pembeli->id ...]))
     * tapi selama ini diam-diam dibuang oleh Eloquent karena kolomnya belum
     * ada & belum ada di $fillable — makanya piutang tidak pernah bisa
     * dikelompokkan per pembeli secara akurat, cuma lewat teks nama_customer.
     *
     * Data lama (penjualan sebelum kolom ini ada) dibiarkan NULL — buku
     * pembantu piutang untuk transaksi lama tetap fallback ke nama teks.
     */
    public function up(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->foreignId('pembeli_id')
                ->nullable()
                ->after('nama_customer')
                ->constrained('pembelis')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pembeli_id');
        });
    }
};