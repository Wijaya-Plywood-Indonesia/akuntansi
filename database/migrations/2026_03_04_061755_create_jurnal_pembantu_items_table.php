<?php

use Illuminate\Database\Console\Migrations\StatusCommand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jurnal_pembantu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurnal_pembantu_header_id')
                ->constrained('jurnal_pembantu_headers')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('urut')->default(1); // Urutan item dalam header

            // --- Identitas Barang ---
            $table->string('nama', 255)->nullable();          // Nama barang/bahan/orang
            $table->string('jenis_pihak', 20)->nullable();
            // Klasifikasi: 'pelanggan', 'pemasok', 'karyawan', 'lain'

            $table->string('no_dokumen', 100)->nullable();    // No. surat jalan, dsb.
            $table->text('keterangan')->nullable();    // Keterangan item

            // --- Dimensi (untuk veneer/kayu) ---
            $table->string('ukuran', 255)->nullable();          // Tebal dalam milimeter
            $table->string('kualitas', 20)->nullable();       // 1/2/3/4/5/AF/dll

            // --- Kuantitas ---
            $table->decimal('banyak', 12, 6)->nullable();     // Jumlah lembar/unit
            $table->decimal('m3', 14, 6)->nullable();         // Kubikasi (m³)
            $table->decimal('harga', 20, 6)->default(0);      // Harga per satuan

            // --- Logika Hitung ---
            // 'k' = ×m3  |  'b' = ×banyak  |  null = nilai langsung
            $table->char('hit_kbk', 1)->nullable();

            //status detail jurnal bantuan
            $table->boolean('status')->default(1)->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_pembantu_items');
    }
};