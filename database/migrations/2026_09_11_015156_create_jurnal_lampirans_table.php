<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lampiran (foto nota/bukti) untuk sebuah TRANSAKSI JURNAL — disimpan
     * TERPISAH dari jurnal_pembantu_headers maupun jurnal_umum, dan
     * dikaitkan lewat nomor "jurnal" (integer), bukan per baris D/K.
     *
     * Kenapa begini, bukan nambah kolom "lampiran" langsung di
     * jurnal_pembantu_headers / jurnal_umum:
     * 1. Satu nomor jurnal bisa punya BANYAK baris (debit & kredit,
     *    kadang lebih dari 2 baris kalau split per barang). Kalau kolom
     *    lampiran ditaruh per baris, fotonya harus diduplikasi ke semua
     *    baris itu — boros & rawan tidak sinkron kalau diedit di satu
     *    baris tapi tidak di baris lain.
     * 2. Nomor "jurnal" TETAP SAMA dari sebelum diposting (tabel
     *    jurnal_pembantu_headers) sampai SESUDAH diposting (tabel
     *    jurnal_umum) — lihat PostingJurnalPembantuService::class. Jadi
     *    cukup 1 tabel lampiran yang dikaitkan lewat nomor itu, otomatis
     *    "terlihat" di kedua tempat tanpa perlu disalin ulang.
     */
    public function up(): void
    {
        Schema::create('jurnal_lampirans', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('jurnal')->unique();
            // Array path file (multi-foto), sama seperti kolom "foto" di
            // tabel pembelians — disimpan di disk public/jurnal-lampiran.
            $table->json('paths')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_lampirans');
    }
};