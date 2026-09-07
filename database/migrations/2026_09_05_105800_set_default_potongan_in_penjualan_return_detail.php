<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('penjualan_return_detail', function (Blueprint $table) {
            $table->decimal('potongan', 15, 2)->default(0)->change();
            $table->decimal('harga_awal', 15, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('penjualan_return_detail', function (Blueprint $table) {
            $table->decimal('potongan', 15, 2)->change();
            $table->decimal('harga_awal', 15, 2)->change();
        });
    }
};
