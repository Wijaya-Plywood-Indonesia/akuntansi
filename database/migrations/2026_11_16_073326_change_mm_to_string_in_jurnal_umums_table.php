<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom `mm` sebelumnya INTEGER, sehingga input seperti "4 dim" terpotong
 * jadi 4. Diubah ke VARCHAR supaya bisa menyimpan teks bebas
 * (mis. "4 dim", "ban belakang fuso"). Data lama (angka) tetap aman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal_umums', function (Blueprint $table) {
            $table->string('mm', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_umums', function (Blueprint $table) {
            // Peringatan: nilai non-angka akan hilang/berubah saat rollback.
            $table->integer('mm')->nullable()->change();
        });
    }
};