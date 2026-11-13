<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('barang_id')->nullable()->constrained('barangs')->nullOnDelete();
            $table->string('kode_barang')->nullable();
            $table->string('nama_barang')->nullable();
            $table->string('satuan')->nullable();
            $table->decimal('qty', 18, 4)->default(0);
            $table->decimal('kubikasi', 18, 4)->default(0);
            $table->string('hitung_dari')->default('qty'); // 'qty' or 'm3'
            $table->decimal('harga_beli', 18, 2)->default(0);
            $table->decimal('diskon', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
