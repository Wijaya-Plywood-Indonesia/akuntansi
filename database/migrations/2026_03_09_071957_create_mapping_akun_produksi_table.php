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
        Schema::create('mapping_akun_produksi', function (Blueprint $table) {
            $table->id();

            // FK ke kelompok
            $table->foreignId('id_kelompok')
                ->constrained('kelompok_mapping_akun')
                ->cascadeOnDelete();

            $table->foreignId('sub_anak_akun_id')
                ->constrained('sub_anak_akuns')
                ->restrictOnDelete();

            $table->enum('posisi_jurnal', ['debet', 'kredit', 'keduanya']);

            $table->tinyInteger('urutan')->default(1)
                ->comment('Urutan baris item dalam kelompok');

            $table->text('keterangan')->nullable();

            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');

            // user stamps
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
        Schema::dropIfExists('mapping_akun_produksi');
    }
};
