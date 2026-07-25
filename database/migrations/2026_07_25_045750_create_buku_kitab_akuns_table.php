<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buku_kitab_akuns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buku_kitab_id')
                ->constrained('buku_kitabs')
                ->cascadeOnDelete();
            $table->unsignedInteger('urut')->default(1);
            $table->string('no_akun');       // kode_sub_anak_akun / kode_anak_akun
            $table->string('nama_akun');     // disalin dari master akun saat dipilih
            $table->enum('posisi', ['d', 'k']);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buku_kitab_akuns');
    }
};