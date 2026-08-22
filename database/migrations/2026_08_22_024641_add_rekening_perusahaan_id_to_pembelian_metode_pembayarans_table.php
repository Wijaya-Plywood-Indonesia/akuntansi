<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pembelian_metode_pembayarans', function (Blueprint $table) {
            $table->foreignId('rekening_perusahaan_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('rekening_perusahaan')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pembelian_metode_pembayarans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rekening_perusahaan_id');
        });
    }
};