<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            // Mengubah kolom toko_id menjadi boleh kosong (nullable)
            $table->unsignedBigInteger('toko_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            // Kembalikan kolom jika sewaktu-waktu di-rollback
            $table->unsignedBigInteger('toko_id')->nullable();
        });
    }
};