<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ambil data lama dari pivot akun_group_sub_anak_akun
        if (Schema::hasTable('akun_group_sub_anak_akun')) {
            $oldRelations = DB::table('akun_group_sub_anak_akun')->get();

            foreach ($oldRelations as $relation) {
                // Cari id_anak_akun dari sub_anak_akuns
                $subAnakAkun = DB::table('sub_anak_akuns')
                    ->where('id', $relation->sub_anak_akun_id)
                    ->first();

                if ($subAnakAkun && $subAnakAkun->id_anak_akun) {
                    // Masukkan ke akun_group_anak_akun jika belum ada
                    $exists = DB::table('akun_group_anak_akun')
                        ->where('akun_group_id', $relation->akun_group_id)
                        ->where('anak_akun_id', $subAnakAkun->id_anak_akun)
                        ->exists();

                    if (!$exists) {
                        DB::table('akun_group_anak_akun')->insert([
                            'akun_group_id' => $relation->akun_group_id,
                            'anak_akun_id' => $subAnakAkun->id_anak_akun,
                            'created_at' => $relation->created_at ?? now(),
                            'updated_at' => $relation->updated_at ?? now(),
                        ]);
                    }
                }
            }

            // 2. Drop tabel lama karena sudah dipindahkan
            Schema::dropIfExists('akun_group_sub_anak_akun');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the table
        if (!Schema::hasTable('akun_group_sub_anak_akun')) {
            Schema::create('akun_group_sub_anak_akun', function (Blueprint $table) {
                $table->id();
                $table->foreignId('akun_group_id')
                    ->constrained('akun_groups')
                    ->cascadeOnDelete();
                $table->foreignId('sub_anak_akun_id')
                    ->constrained('sub_anak_akuns')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['akun_group_id', 'sub_anak_akun_id']);
            });
        }
    }
};
