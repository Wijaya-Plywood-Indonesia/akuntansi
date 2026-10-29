<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom id_supplier (nullable) di jurnal_umums, pasangan id_pembeli:
     * dipakai Buku Pembantu Utang (per Supplier), mis. akun 2102.0.
     * Data lama dibiarkan NULL — buku pembantu fallback ke kolom teks `nama`.
     */
    public function up(): void
    {
        Schema::table('jurnal_umums', function (Blueprint $table) {
            $table->foreignId('id_supplier')
                ->nullable()
                ->after('id_pembeli')
                ->constrained('suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_umums', function (Blueprint $table) {
            $table->dropForeign(['id_supplier']);
            $table->dropColumn('id_supplier');
        });
    }
};
