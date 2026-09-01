<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom ini terpisah dari `tipe` (yang khusus dipakai Laba Rugi).
     * Sebuah AkunGroup yang sama boleh punya `tipe` DAN `kategori_arus_kas`
     * sekaligus — mis. grup "Penjualan" bisa tipe=pendapatan (untuk Laba
     * Rugi) sekaligus kategori_arus_kas=penjualan (untuk Rekap Arus Kas).
     * Admin tinggal menandai grup yang sudah ada lewat menu Akun Group,
     * tidak perlu membuat struktur akun baru.
     */
    public function up(): void
    {
        Schema::table('akun_groups', function (Blueprint $table) {
            $table->enum('kategori_arus_kas', [
                'penjualan',        // Kas masuk dari penjualan
                'pendanaan',        // Kas masuk/keluar: modal, utang, piutang, pinjaman
                'pembelian_stok',   // Kas keluar untuk pembelian barang / persediaan
                'produksi',         // Kas keluar untuk biaya produksi
                'beban_usaha',      // Kas keluar untuk beban operasional
                'lainnya',          // Tidak masuk kategori manapun di atas
            ])->nullable()->after('tipe');
        });
    }

    public function down(): void
    {
        Schema::table('akun_groups', function (Blueprint $table) {
            $table->dropColumn('kategori_arus_kas');
        });
    }
};