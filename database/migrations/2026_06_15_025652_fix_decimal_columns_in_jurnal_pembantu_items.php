<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jurnal_pembantu_items', function (Blueprint $table) {
            // jumlah: nilai rupiah total (bisa miliaran)
            $table->decimal('jumlah', 20, 4)->nullable()->change();

            // banyak: quantity lembar/unit
            $table->decimal('banyak', 12, 4)->nullable()->change();

            // m3: volume kayu (presisi tinggi)
            $table->decimal('m3', 16, 6)->nullable()->change();

            // harga: sudah decimal(20,6) — sudah oke, skip
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_pembantu_items', function (Blueprint $table) {
            $table->decimal('jumlah', 12, 6)->nullable()->change();
            $table->decimal('banyak', 12, 6)->nullable()->change();
            $table->decimal('m3', 14, 6)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
};
