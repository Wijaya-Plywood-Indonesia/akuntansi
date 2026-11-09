<?php

use App\Models\JurnalUmum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nama tabel diambil dari model, bukan ditebak.
        $tabelJU = (new JurnalUmum)->getTable();
        $tabelHeader = 'jurnal_pembantu_headers';

        // Hitung nomor terbesar DULU, sebelum membuat tabel,
        // supaya kalau ada error tidak ada tabel setengah jadi.
        $maxHeader = (int) DB::table($tabelHeader)->max('jurnal');
        $maxJU = (int) DB::table($tabelJU)->max('jurnal');
        $maxJurnal = max($maxHeader, $maxJU);
        $maxNoJP = (int) DB::table($tabelHeader)->max('no_jurnal_pembantu');

        Schema::create('nomor_counters', function (Blueprint $t) {
            $t->string('nama', 50)->primary(); // 'jurnal', 'no_jp'
            $t->unsignedBigInteger('nilai')->default(0);
            $t->timestamps();
        });

        DB::table('nomor_counters')->insert([
            ['nama' => 'jurnal', 'nilai' => $maxJurnal, 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'no_jp',  'nilai' => $maxNoJP,   'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('nomor_counters');
    }
};
