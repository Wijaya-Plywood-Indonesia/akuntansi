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
            $table->date('tgl');
            $table->integer('jurnal')->nullable();
            // Form
            $table->string('no_akun');
            $table->string('nama_akun')->nullable();
            $table->string('keterangan')->nullable();
            // Revisi
            $table->integer('banyak')->nullable()->default(1);
            // Tambahan
            $table->decimal('m3', 15, 4)->nullable();
            $table->decimal('harga', 20, 2)->nullable();
            $table->string('map', 5)->nullable();

            // Tambahan form 
            $table->string('no-dokumen')->nullable();
            $table->string('nama')->nullable();
            $table->integer('mm')->nullable();
            $table->string('hit_kbk', 10)->nullable();
            $table->$table->timestamps();
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
