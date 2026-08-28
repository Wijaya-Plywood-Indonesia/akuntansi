<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom variabel_nilai — label pendek yang bilang nominal
     * baris jurnal ini diambil dari kunci apa di $context saat engine
     * (buatJurnalDariKitab) menyusun jurnal. Nullable dulu supaya baris
     * lama yang belum diisi tidak error, tapi WAJIB diisi manual untuk
     * semua baris yang sudah ada sebelum engine dipakai (lihat README).
     */
    public function up(): void
    {
        Schema::table('buku_kitab_akuns', function (Blueprint $table) {
            $table->string('variabel_nilai')
                ->nullable()
                ->after('posisi')
                ->comment('Kunci di $context yang jadi sumber nominal baris ini, mis. hpp, ppn, kas_diterima');
        });
    }

    public function down(): void
    {
        Schema::table('buku_kitab_akuns', function (Blueprint $table) {
            $table->dropColumn('variabel_nilai');
        });
    }
};