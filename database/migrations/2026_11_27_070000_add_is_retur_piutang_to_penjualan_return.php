<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migrasi: Tambah kolom `is_retur_piutang` di tabel penjualan_return.
 *
 * Kolom ini dipakai untuk menandai bahwa transaksi retur terjadi pada nota
 * yang BELUM LUNAS (status COD/PIUTANG). Ketika divalidasi, jurnal yang
 * terbentuk akan mengurangi Piutang Usaha (K) alih-alih mencatat kas masuk.
 * Saat pelunasan nota tersebut, nominal tagihan akan otomatis dikurangi
 * sebesar nilai retur yang sudah divalidasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penjualan_return', function (Blueprint $table) {
            // true  = retur terjadi pada nota belum lunas (jurnal: nilai_retur D, piutang_usaha K)
            // false = retur normal pada nota LUNAS (jurnal: nilai_retur D, kas/bank K)
            $table->boolean('is_retur_piutang')->default(false)->after('akun_pengembalian');
        });
    }

    public function down(): void
    {
        Schema::table('penjualan_return', function (Blueprint $table) {
            $table->dropColumn('is_retur_piutang');
        });
    }
};
