<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buku_kitabs', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();        // mis. PENJUALAN_LOGCORE
            $table->string('nama');                   // mis. Penjualan Logcore
            $table->string('kategori')->nullable();    // mis. Penjualan, Pembelian, Produksi
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buku_kitabs');
    }
};