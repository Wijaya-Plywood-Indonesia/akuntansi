<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jurnal_pembantu_items', function (Blueprint $table) {
            $table->foreignId('id_barang')
                ->nullable()
                ->after('nama_barang')
                ->constrained('barangs')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jurnal_pembantu_items', function (Blueprint $table) {
            $table->dropForeign(['id_barang']);
            $table->dropColumn('id_barang');
        });
    }
};
