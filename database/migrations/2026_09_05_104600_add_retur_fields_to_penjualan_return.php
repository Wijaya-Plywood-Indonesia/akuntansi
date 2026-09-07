<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('penjualan_return', function (Blueprint $table) {
            $table->foreignId('penjualan_id')->nullable()->after('id')->constrained('penjualans')->nullOnDelete();
            $table->string('no_retur')->nullable()->unique()->after('penjualan_id');
            $table->string('jenis_retur')->nullable()->after('status_return'); // NORMAL, SEBAGIAN, DP_NORMAL, DP_SEBAGIAN
            $table->string('kode_kitab')->nullable()->after('jenis_retur');
            $table->string('akun_pengembalian')->nullable()->after('kode_kitab');
            $table->decimal('sub_total', 15, 2)->default(0)->after('kendaraan');
            $table->decimal('ppn_nominal', 15, 2)->default(0)->after('sub_total');
            $table->string('metode_pembayaran')->nullable()->change();
        });

        Schema::table('penjualan_return_detail', function (Blueprint $table) {
            $table->foreignId('penjualan_detail_id')->nullable()->after('id_barang')->constrained('penjualan_details')->nullOnDelete();
            $table->decimal('harga_beli', 15, 2)->default(0)->after('harga_awal');
        });
    }

    public function down(): void
    {
        Schema::table('penjualan_return', function (Blueprint $table) {
            $table->dropForeign(['penjualan_id']);
            $table->dropColumn([
                'penjualan_id',
                'no_retur',
                'jenis_retur',
                'kode_kitab',
                'akun_pengembalian',
                'sub_total',
                'ppn_nominal',
            ]);
        });

        Schema::table('penjualan_return_detail', function (Blueprint $table) {
            $table->dropForeign(['penjualan_detail_id']);
            $table->dropColumn([
                'penjualan_detail_id',
                'harga_beli',
            ]);
        });
    }
};
