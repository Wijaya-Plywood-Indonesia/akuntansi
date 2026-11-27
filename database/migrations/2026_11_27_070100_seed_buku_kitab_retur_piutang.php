<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed template Buku Kitab untuk Retur Piutang.
 *
 * Template ini dipakai saat retur terjadi pada nota yang BELUM LUNAS (COD/PIUTANG).
 * Jurnal yang terbentuk:
 *
 *   NON-PPN:
 *     D: Retur Penjualan  (nilai_retur)       → kode akun retur penjualan
 *     D: HPP              (hpp)                → persediaan masuk kembali (K)
 *     K: Piutang Usaha    (piutang_usaha)      → mengurangi piutang customer
 *     K: Persediaan BJ    (persediaan_barang_jadi) → barang masuk kembali
 *
 *   PPN:
 *     D: Retur Penjualan  (nilai_retur)
 *     D: HPP              (hpp)
 *     D: PPN Keluaran     (ppn_keluaran)       → reversal PPN output
 *     K: Piutang Usaha    (piutang_usaha)
 *     K: Persediaan BJ    (persediaan_barang_jadi)
 *
 * Admin dapat menyesuaikan kode akun via menu Buku Kitab jika diperlukan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ─── NON PPN ────────────────────────────────────────────────────────────
        $existsNonPpn = DB::table('buku_kitabs')->where('kode', 'retur_piutang_non_ppn')->exists();
        if (! $existsNonPpn) {
            $kitabNonPpnId = DB::table('buku_kitabs')->insertGetId([
                'kode'       => 'retur_piutang_non_ppn',
                'nama'       => 'RETUR PIUTANG NON PPN (Kurangi Piutang Customer)',
                'is_active'  => true,
                'keterangan' => 'Template retur penjualan untuk nota yang belum lunas (COD/PIUTANG). Mengurangi piutang usaha, bukan mencatat kas masuk.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $barisNonPpn = [
                // DEBIT: Retur Penjualan (pengurang pendapatan)
                [
                    'buku_kitab_id'   => $kitabNonPpnId,
                    'urut'            => 1,
                    'no_akun'         => '4111.0',
                    'nama_akun'       => 'Retur Penjualan',
                    'posisi'          => 'd',
                    'variabel_nilai'  => 'nilai_retur',
                    'keterangan'      => 'Retur Penjualan | Ref: {no_nota} | Nota Belum Lunas',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ],
                // DEBIT: HPP (barang masuk kembali di sisi beban HPP — dikurangi)
                // Catatan: posisi D pada HPP di sisi retur berarti HPP berkurang (reversal HPP penjualan)
                [
                    'buku_kitab_id'   => $kitabNonPpnId,
                    'urut'            => 2,
                    'no_akun'         => '1131.0',
                    'nama_akun'       => 'Persediaan Barang Jadi',
                    'posisi'          => 'd',
                    'variabel_nilai'  => 'persediaan_barang_jadi',
                    'keterangan'      => 'Barang Retur Masuk Kembali ke Stok',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ],
                // KREDIT: Piutang Usaha berkurang
                [
                    'buku_kitab_id'   => $kitabNonPpnId,
                    'urut'            => 3,
                    'no_akun'         => '1122.0',
                    'nama_akun'       => 'Piutang Usaha',
                    'posisi'          => 'k',
                    'variabel_nilai'  => 'piutang_usaha',
                    'keterangan'      => 'Pengurangan Piutang Customer | Retur Nota Belum Lunas',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ],
                // KREDIT: HPP (barang dikembalikan, HPP penjualan berkurang)
                [
                    'buku_kitab_id'   => $kitabNonPpnId,
                    'urut'            => 4,
                    'no_akun'         => '5111.0',
                    'nama_akun'       => 'Harga Pokok Penjualan',
                    'posisi'          => 'k',
                    'variabel_nilai'  => 'hpp',
                    'keterangan'      => 'Reversal HPP — Barang Retur Diterima Kembali',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ],
            ];

            DB::table('buku_kitab_akuns')->insert($barisNonPpn);
        }

        // ─── PPN ────────────────────────────────────────────────────────────────
        $existsPpn = DB::table('buku_kitabs')->where('kode', 'retur_piutang_ppn')->exists();
        if (! $existsPpn) {
            $kitabPpnId = DB::table('buku_kitabs')->insertGetId([
                'kode'       => 'retur_piutang_ppn',
                'nama'       => 'RETUR PIUTANG PPN (Kurangi Piutang Customer + Reversal PPN)',
                'is_active'  => true,
                'keterangan' => 'Template retur penjualan PPN untuk nota yang belum lunas. Mengurangi piutang dan mereklas PPN Keluaran.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $barisPpn = [
                [
                    'buku_kitab_id'   => $kitabPpnId,
                    'urut'            => 1,
                    'no_akun'         => '4111.0',
                    'nama_akun'       => 'Retur Penjualan',
                    'posisi'          => 'd',
                    'variabel_nilai'  => 'nilai_retur',
                    'keterangan'      => 'Retur Penjualan | Ref: {no_nota} | Nota Belum Lunas (PPN)',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ],
                [
                    'buku_kitab_id'   => $kitabPpnId,
                    'urut'            => 2,
                    'no_akun'         => '2211.0',
                    'nama_akun'       => 'PPN Keluaran',
                    'posisi'          => 'd',
                    'variabel_nilai'  => 'ppn_keluaran',
                    'keterangan'      => 'Reversal PPN Keluaran — Retur Nota Belum Lunas',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ],
                [
                    'buku_kitab_id'   => $kitabPpnId,
                    'urut'            => 3,
                    'no_akun'         => '1131.0',
                    'nama_akun'       => 'Persediaan Barang Jadi',
                    'posisi'          => 'd',
                    'variabel_nilai'  => 'persediaan_barang_jadi',
                    'keterangan'      => 'Barang Retur Masuk Kembali ke Stok (PPN)',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ],
                [
                    'buku_kitab_id'   => $kitabPpnId,
                    'urut'            => 4,
                    'no_akun'         => '1122.0',
                    'nama_akun'       => 'Piutang Usaha',
                    'posisi'          => 'k',
                    'variabel_nilai'  => 'piutang_usaha',
                    'keterangan'      => 'Pengurangan Piutang Customer | Retur Nota Belum Lunas (PPN)',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ],
                [
                    'buku_kitab_id'   => $kitabPpnId,
                    'urut'            => 5,
                    'no_akun'         => '5111.0',
                    'nama_akun'       => 'Harga Pokok Penjualan',
                    'posisi'          => 'k',
                    'variabel_nilai'  => 'hpp',
                    'keterangan'      => 'Reversal HPP — Barang Retur Diterima Kembali (PPN)',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ],
            ];

            DB::table('buku_kitab_akuns')->insert($barisPpn);
        }
    }

    public function down(): void
    {
        $kodes = ['retur_piutang_non_ppn', 'retur_piutang_ppn'];
        $ids = DB::table('buku_kitabs')->whereIn('kode', $kodes)->pluck('id');
        DB::table('buku_kitab_akuns')->whereIn('buku_kitab_id', $ids)->delete();
        DB::table('buku_kitabs')->whereIn('kode', $kodes)->delete();
    }
};
