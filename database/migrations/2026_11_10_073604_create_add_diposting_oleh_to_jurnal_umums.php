<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal_umums', function (Blueprint $table) {
            $table->unsignedBigInteger('diposting_oleh')->nullable()->after('map');
            $table->index('diposting_oleh');
        });

        // Isi data lama: ambil pemosting dari jurnal pembantu header yang
        // nomor jurnalnya sama (nomor header = nomor Jurnal Umum setelah posting).
        // Baris tanpa header (input manual / saldo awal) dibiarkan NULL.
        DB::statement("
            UPDATE jurnal_umums ju
            JOIN (
                SELECT jurnal, MAX(diposting_oleh) AS uid
                FROM jurnal_pembantu_headers
                WHERE diposting_oleh IS NOT NULL
                GROUP BY jurnal
            ) h ON h.jurnal = ju.jurnal
            SET ju.diposting_oleh = h.uid
            WHERE ju.diposting_oleh IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('jurnal_umums', function (Blueprint $table) {
            $table->dropIndex(['diposting_oleh']);
            $table->dropColumn('diposting_oleh');
        });
    }
};