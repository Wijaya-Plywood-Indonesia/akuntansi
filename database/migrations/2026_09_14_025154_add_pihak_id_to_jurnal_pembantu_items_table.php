<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal_pembantu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('pihak_id')
                ->nullable()
                ->after('jenis_pihak');
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_pembantu_items', function (Blueprint $table) {
            $table->dropColumn('pihak_id');
        });
    }
};