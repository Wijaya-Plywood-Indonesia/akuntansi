<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $templates = [
            'penjualan_triplek_ppn',
            'penjualan_triplek_non_ppn',
            'penjualan_bayar_dimuka_dikirim',
            'penjualan_dp_diterima_tunai',
            'penjualan_dp_diterima_bank_99',
            'penjualan_dp_diterima_bank_wahana',
            'penjualan_dp_diterima_bank_wpi',
            'penjualan_dp_diterima_bank_industri',
            'penjualan_dp_diterima_bank_intan',
            'penjualan_dp_diterima_bank_bu_eddy',
        ];

        foreach ($templates as $kodeKitab) {
            $kitab = DB::table('buku_kitabs')->where('kode', $kodeKitab)->first();
            if (! $kitab) {
                continue;
            }

            $exists = DB::table('buku_kitab_akuns')
                ->where('buku_kitab_id', $kitab->id)
                ->where('variabel_nilai', 'pendapatan_lainnya')
                ->exists();

            if (! $exists) {
                $maxUrut = DB::table('buku_kitab_akuns')
                    ->where('buku_kitab_id', $kitab->id)
                    ->max('urut') ?? 0;

                DB::table('buku_kitab_akuns')->insert([
                    'buku_kitab_id' => $kitab->id,
                    'urut' => $maxUrut + 1,
                    'no_akun' => '4199.0',
                    'nama_akun' => 'Pendapatan Usaha Lainnya',
                    'posisi' => 'k',
                    'variabel_nilai' => 'pendapatan_lainnya',
                    'keterangan' => 'Pendapatan Usaha Lainnya (Ongkir)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $templates = [
            'penjualan_triplek_ppn',
            'penjualan_triplek_non_ppn',
            'penjualan_bayar_dimuka_dikirim',
            'penjualan_dp_diterima_tunai',
            'penjualan_dp_diterima_bank_99',
            'penjualan_dp_diterima_bank_wahana',
            'penjualan_dp_diterima_bank_wpi',
            'penjualan_dp_diterima_bank_industri',
            'penjualan_dp_diterima_bank_intan',
            'penjualan_dp_diterima_bank_bu_eddy',
        ];

        $kitabIds = DB::table('buku_kitabs')->whereIn('kode', $templates)->pluck('id');

        DB::table('buku_kitab_akuns')
            ->whereIn('buku_kitab_id', $kitabIds)
            ->where('variabel_nilai', 'pendapatan_lainnya')
            ->where('no_akun', '4199.0')
            ->delete();
    }
};
