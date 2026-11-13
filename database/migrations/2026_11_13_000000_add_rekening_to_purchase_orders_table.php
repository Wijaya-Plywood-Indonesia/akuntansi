<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('supplier_bank')->nullable()->after('supplier_address');
            $table->string('supplier_no_rek')->nullable()->after('supplier_bank');
            $table->string('supplier_atas_nama')->nullable()->after('supplier_no_rek');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['supplier_bank', 'supplier_no_rek', 'supplier_atas_nama']);
        });
    }
};
