<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembelians', function (Blueprint $table) {
            // Snapshot total Uang Muka Pembelian (DP) yang sudah terkumpul
            // PERSIS di momen "Konfirmasi Barang Datang" untuk nota jenis DP
            // yang saat itu BELUM lunas (barang datang duluan, sisa dicicil
            // belakangan). Diisi sekali oleh
            // PembelianKedatanganService::konfirmasiBarangDatangDp() dan
            // dibaca lagi oleh
            // JurnalPembelianTriplekService::buatJurnalBayarHutangDp() saat
            // cicilan TERAKHIR (sisaTagihan() jadi 0) untuk tahu berapa
            // nominal Uang Muka Pembelian yang harus dibalik/ditutup.
            //
            // Kenapa snapshot, bukan hitung ulang tiap saat: begitu barang
            // dikonfirmasi datang, Pembelian::bisaTambahDp() otomatis
            // menutup kemungkinan nambah DP baru — jadi nilai DP yang sudah
            // terkumpul di titik itu FINAL, tidak akan berubah lagi. Aman
            // disimpan sekali daripada di-query ulang berdasarkan timestamp
            // (rawan meleset kalau ada baris dengan created_at yang sama).
            //
            // Null = bukan kasus "barang datang belum lunas" (baik karena
            // nota-nya bukan DP, atau DP-nya lunas sekaligus saat barang
            // datang / lewat BAYAR_DIMUKA).
            $table->decimal('dp_terkumpul_saat_barang_datang', 18, 2)->nullable()
                ->after('barang_diterima_by');
        });
    }

    public function down(): void
    {
        Schema::table('pembelians', function (Blueprint $table) {
            $table->dropColumn('dp_terkumpul_saat_barang_datang');
        });
    }
};