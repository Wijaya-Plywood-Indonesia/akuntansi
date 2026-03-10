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
            $table->string('kode_akun', 20);
            $table->bigInteger('id_kelompok');
            $table->enum('posisi_jurnal', ['debet', 'kredit', 'keduanya']);
            $table->tinyInteger('urutan')->default(1)
                ->comment('Urutan baris item dalam kelompok');
            $table->text('keterangan')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');

            // ── User Stamps ──────────────────────────────────
            $table->foreignId('dibuat_oleh')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('diedit_oleh')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            // ── Foreign Keys ─────────────────────────────────
            $table->foreign('id_kelompok')
                ->references('id')
                ->on('kelompok_mapping_akun')
                ->onDelete('cascade');

            $table->foreign('kode_akun')
                ->references('kode_sub_anak_akun')
                ->on('sub_anak_akuns')
                ->onDelete('restrict'); // akun tidak bisa dihapus selama masih dipakai
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
