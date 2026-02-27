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
        Schema::create('jurnal_umums', function (Blueprint $table) {
            $table->id();
            // Header
            $table->date('tanggal');
            $table->integer('nomor_jurnal')->nullable();
            // Form
            $table->string('nomor_akun');
            $table->string('nama_akun')->nullable();
            $table->string('keterangan')->nullable();
            $table->decimal('banyak', 15, 4)->nullable()->default(1);
            $table->decimal('harga', 20, 2)->nullable();
            // Tracking for journaling
            $table->string('created_by')->nullable();
            $table->string('status')->default('belum sinkron');
            $table->dateTime('synced_at')->nullable();
            $table->string('synced_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jurnal_umums');
    }
};
