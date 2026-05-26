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
        Schema::create('kelompok_mapping_akun', function (Blueprint $table) {
            $table->id();
            $table->string('kode_kelompok', 30)->unique();
            $table->string('nama_kelompok', 100)->nullable();
            //ini buat cadangan kalau nama nya udah banyak
            $table->string('kode_proses', 20)->nullable();
            $table->text('keterangan')->nullable();

            // ── User Stamps ──────────────────────────────────
            $table->foreignId('dibuat_oleh')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('diedit_oleh')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelompok_mapping_akun');
    }
};
