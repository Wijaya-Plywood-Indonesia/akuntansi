<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nomor_po')->nullable()->unique();
            $table->date('tanggal')->nullable();
            $table->date('tanggal_jatuh_tempo')->nullable();
            $table->json('foto')->nullable();

            // Supplier snapshot
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_phone')->nullable();
            $table->text('supplier_address')->nullable();

            // Totals
            $table->decimal('sub_total', 18, 2)->default(0);
            $table->decimal('total_diskon', 18, 2)->default(0);
            $table->decimal('total_ppn', 18, 2)->default(0);
            $table->decimal('ongkir', 18, 2)->default(0);
            $table->decimal('biaya_lain', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);

            // Status: draft, dikirim, disetujui, selesai, dibatalkan
            $table->string('status')->default('draft')->index();

            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
