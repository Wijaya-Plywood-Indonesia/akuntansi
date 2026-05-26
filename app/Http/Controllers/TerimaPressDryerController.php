<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;

class TerimaPressDryerController extends Controller
{
    public function terima(Request $request)
    {
        // 1. Validasi API Key
        if ($request->header('X-API-KEY') !== config('services.produksi_api.key')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->all();
        $produksi = $data['produksi'];
        $tgl = $produksi['tanggal_produksi'];
        $shift = $produksi['shift'];
        $kendala = $produksi['kendala'] ?? null;

        $ketDasar = "Produksi Press Dryer - Shift {$shift} - {$tgl}"
            . ($kendala ? " | Kendala: {$kendala}" : '');

        DB::beginTransaction();
        try {
            $noJurnal = $this->generateNoJurnal();

            // ══════════════════════════════════════════
            // HEADER 1 — DEBET: Veneer Kering (Hasil)
            // ══════════════════════════════════════════
            $headerD1 = JurnalPembantuHeader::create([
                'no_jurnal_pembantu' => $noJurnal,
                'tgl_transaksi' => $tgl,
                'jenis_transaksi' => 'Produksi - Press Dryer',
                'modul_asal' => 'produksi_dryer',
                'jurnal' => $noJurnal,
                'no_akun' => '115-09',
                'nama_akun' => 'Veneer Kering F/B',
                'map' => 'd',
                'keterangan' => $ketDasar,
                'total_nilai' => 0,
                'status' => 'draft',
                'dibuat_oleh' => 1,
            ]);

            $urut = 1;
            foreach ($data['detail_hasil'] ?? [] as $hasil) {
                $m3 = $this->hitungM3($hasil['ukuran'] ?? null, (float) ($hasil['isi'] ?? 0));
                JurnalPembantuItem::create([
                    'jurnal_pembantu_header_id' => $headerD1->id,
                    'urut' => $urut++,
                    'nama' => 'Veneer Kering - ' . ($hasil['jenis_kayu'] ?? '-'),
                    'nama_barang' => $hasil['jenis_kayu'] ?? null,
                    'ukuran' => $hasil['ukuran'] ?? null,
                    'kualitas' => $hasil['kw'] ?? null,
                    'banyak' => $hasil['isi'] ?? 0,
                    'm3' => $m3,
                    'keterangan' => "Palet: " . ($hasil['no_palet'] ?? '-'),
                    'harga' => 0,
                    'status' => 1,
                ]);
            }

            // ══════════════════════════════════════════
            // HEADER 2 — DEBET: Upah Tenaga Kerja
            // ══════════════════════════════════════════
            $headerD2 = JurnalPembantuHeader::create([
                'no_jurnal_pembantu' => $noJurnal,
                'tgl_transaksi' => $tgl,
                'jenis_transaksi' => 'Produksi - Press Dryer',
                'modul_asal' => 'produksi_dryer',
                'jurnal' => $noJurnal,
                'no_akun' => '510-01',
                'nama_akun' => 'Upah Tenaga Kerja',
                'map' => 'd',
                'keterangan' => $ketDasar,
                'total_nilai' => 0,
                'status' => 'draft',
                'dibuat_oleh' => 1,
            ]);

            $urut = 1;
            foreach ($data['detail_pegawai'] ?? [] as $pegawai) {
                JurnalPembantuItem::create([
                    'jurnal_pembantu_header_id' => $headerD2->id,
                    'urut' => $urut++,
                    'nama' => $pegawai['nama_pegawai'] ?? null,
                    'jenis_pihak' => 'karyawan',
                    'nama_pihak' => $pegawai['nama_pegawai'] ?? null,
                    'keterangan' => "Tugas: " . ($pegawai['tugas'] ?? '-')
                        . " | Masuk: " . ($pegawai['masuk'] ?? '-')
                        . " | Pulang: " . ($pegawai['pulang'] ?? '-'),
                    'harga' => 0,
                    'status' => 1,
                ]);
            }

            // ══════════════════════════════════════════
            // HEADER 3 — KREDIT: Veneer Basah (Masuk)
            // ══════════════════════════════════════════
            $headerK1 = JurnalPembantuHeader::create([
                'no_jurnal_pembantu' => $noJurnal,
                'tgl_transaksi' => $tgl,
                'jenis_transaksi' => 'Produksi - Press Dryer',
                'modul_asal' => 'produksi_dryer',
                'jurnal' => $noJurnal,
                'no_akun' => '115-07',
                'nama_akun' => 'Veneer Basah F/B',
                'map' => 'k',
                'keterangan' => $ketDasar,
                'total_nilai' => 0,
                'status' => 'draft',
                'dibuat_oleh' => 1,
            ]);

            $urut = 1;
            foreach ($data['detail_masuk'] ?? [] as $masuk) {
                $m3 = $this->hitungM3($masuk['ukuran'] ?? null, (float) ($masuk['isi'] ?? 0));
                JurnalPembantuItem::create([
                    'jurnal_pembantu_header_id' => $headerK1->id,
                    'urut' => $urut++,
                    'nama' => 'Veneer Basah - ' . ($masuk['jenis_kayu'] ?? '-'),
                    'nama_barang' => $masuk['jenis_kayu'] ?? null,
                    'ukuran' => $masuk['ukuran'] ?? null,
                    'kualitas' => $masuk['kw'] ?? null,
                    'banyak' => $masuk['isi'] ?? 0,
                    'm3' => $m3,
                    'keterangan' => "Palet: " . ($masuk['no_palet'] ?? '-'),
                    'harga' => 0,
                    'status' => 1,
                ]);
            }

            // ══════════════════════════════════════════
            // HEADER 4 — KREDIT: Hutang Gaji
            // ══════════════════════════════════════════
            $headerK2 = JurnalPembantuHeader::create([
                'no_jurnal_pembantu' => $noJurnal,
                'tgl_transaksi' => $tgl,
                'jenis_transaksi' => 'Produksi - Press Dryer',
                'modul_asal' => 'produksi_dryer',
                'jurnal' => $noJurnal,
                'no_akun' => '210-02',
                'nama_akun' => 'Hutang Gaji',
                'map' => 'k',
                'keterangan' => $ketDasar,
                'total_nilai' => 0,
                'status' => 'draft',
                'dibuat_oleh' => 1,
            ]);

            $urut = 1;
            foreach ($data['detail_pegawai'] ?? [] as $pegawai) {
                JurnalPembantuItem::create([
                    'jurnal_pembantu_header_id' => $headerK2->id,
                    'urut' => $urut++,
                    'nama' => $pegawai['nama_pegawai'] ?? null,
                    'jenis_pihak' => 'karyawan',
                    'nama_pihak' => $pegawai['nama_pegawai'] ?? null,
                    'keterangan' => "Hutang gaji: " . ($pegawai['nama_pegawai'] ?? '-'),
                    'harga' => 0,
                    'status' => 1,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data produksi dryer berhasil diterima.',
                'no_jurnal_pembantu' => $noJurnal,
                'headers_dibuat' => 4,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── Hitung M3 dari string ukuran ──────────────────────────────────
    // Format: "244 x 122 x 0.5"
    // Rumus: (p x l x t / 10.000.000) x banyak lembar
    private function hitungM3(?string $ukuran, float $banyak): float
    {
        if (!$ukuran)
            return 0;

        $parts = array_map('floatval', preg_split('/\s*x\s*/i', $ukuran));

        if (count($parts) < 3)
            return 0;

        [$p, $l, $t] = $parts;

        return round(($p * $l * $t / 10000000) * $banyak, 6);
    }

    private function generateNoJurnal(): int
    {
        $last = JurnalPembantuHeader::max('no_jurnal_pembantu');
        return ($last ?? 0) + 1;
    }
}