<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_pembelians', function (Blueprint $table) {
            $table->decimal('kubikasi', 10, 4)->default(0)->after('qty');
            $table->string('hitung_dari', 10)->default('qty')->after('kubikasi'); // 'qty' atau 'm3'
        });
    }

    public function down(): void
    {
        Schema::table('detail_pembelians', function (Blueprint $table) {
            $table->dropColumn(['kubikasi', 'hitung_dari']);
        });
    }
};