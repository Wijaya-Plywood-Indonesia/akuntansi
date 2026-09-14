<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom tipe_buku_pembantu di sub_anak_akuns supaya sistem
     * tahu, ketika sebuah sub akun diklik di tree COA, modal buku pembantu
     * MANA yang harus ditampilkan:
     *   - null      -> default, coba tampilkan "Barang Terkait" (kalau ada)
     *   - 'piutang' -> tampilkan "Buku Pembantu Piutang" (per pembeli)
     *
     * Nilainya di-set manual lewat form Sub Anak Akun untuk akun-akun
     * seperti 1122/1124/1125/1181 (Piutang Usaha, dsb).
     */
    public function up(): void
    {
        Schema::table('sub_anak_akuns', function (Blueprint $table) {
            $table->string('tipe_buku_pembantu')
                ->nullable()
                ->after('saldo_normal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_anak_akuns', function (Blueprint $table) {
            $table->dropColumn('tipe_buku_pembantu');
        });
    }
};