<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->unsignedBigInteger('sub_total')->default(0)->after('total');
            $table->decimal('ppn_persen', 5, 2)->default(0)->after('sub_total');
            $table->unsignedBigInteger('ppn_nominal')->default(0)->after('ppn_persen');
        });
    }

    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropColumn(['sub_total', 'ppn_persen', 'ppn_nominal']);
        });
    }
};
