<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToArray;

// Kita buat class kecil di dalam controller untuk memparsing Excel menjadi Array
class DataProduksiImport implements ToArray
{
    public function array(array $array)
    {
        return $array;
    }
}

class JurnalProduksiController extends Controller
{
    public function importExcel(Request $request)
    {
        // 1. Validasi file
        $request->validate([
            'file_excel' => 'required|mimes:xlsx,csv,xls|max:2048'
        ], [
            'file_excel.required' => 'Pilih file Excel terlebih dahulu.',
            'file_excel.mimes' => 'Format file harus xlsx, xls, atau csv.'
        ]);

        try {
            // 2. Baca data dari file Excel
            $data = Excel::toArray(new DataProduksiImport, $request->file('file_excel'))[0];
            
            $currentNoJurnal = null;
            $countImported = 0;

            DB::beginTransaction();

            foreach ($data as $index => $row) {
                // Pastikan baris memiliki data
                if (empty($row[0])) continue;

                // Deteksi baris yang berisi "No. Jurnal: ROT/..."
                if (str_starts_with((string)$row[0], 'No. Jurnal:')) {
                    $currentNoJurnal = trim(str_replace('No. Jurnal:', '', $row[0]));
                    continue; 
                }

                // Lewati baris header tabel (Nama Akun, tgl, dll)
                if (strtolower(trim($row[0])) == 'nama akun') continue;

                // Pastikan kolom "map" (index 8) berisi 'd' atau 'k' untuk validasi baris transaksi
                $map = strtolower(trim($row[8] ?? ''));
                if (!in_array($map, ['d', 'k'])) continue;

                // Parsing Tanggal (mengatasi format teks '03-06-2026' atau format tanggal bawaan Excel)
                $tanggalRaw = $row[1];
                $tanggal = date('Y-m-d'); // fallback hari ini
                if (is_numeric($tanggalRaw)) {
                    // Jika format date dari excel terbaca sebagai angka (Excel Serial Date)
                    $tanggal = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tanggalRaw)->format('Y-m-d');
                } else {
                    // Jika teks seperti 03-06-2026
                    $tanggal = date('Y-m-d', strtotime($tanggalRaw));
                }

                // 3. Simpan Header
                // Karena satu jurnal bisa memiliki banyak baris, kita gunakan firstOrCreate
                // agar tidak duplikat membuat header untuk nomor jurnal yang sama
                $header = JurnalPembantuHeader::firstOrCreate(
                    [
                        'jurnal' => $currentNoJurnal,
                        'no_akun' => $row[3], // Kolom No Akun
                        'map' => $map,
                    ],
                    [
                        'no_jurnal_pembantu' => $currentNoJurnal, 
                        'tgl_transaksi' => $tanggal,
                        'jenis_transaksi' => 'produksi',
                        'modul_asal' => 'web_kayu',
                        'nama_akun' => $row[0], // Kolom Nama Akun
                        'status' => JurnalPembantuHeader::STATUS_DRAFT,
                        'dibuat_oleh' => auth()->id() ?? 1,
                    ]
                );

                // 4. Simpan Item
                JurnalPembantuItem::create([
                    'jurnal_pembantu_header_id' => $header->id,
                    'nama_barang' => $row[6] ?? null, // Kolom Nama (misal: kupasan)
                    'keterangan' => $row[7] ?? null,  // Kolom Keterangan (misal: KW 2 - lahan D...)
                    'hit_kbk' => trim($row[9] ?? ''), // Kolom hit kbk (m, b)
                    'banyak' => !empty($row[10]) ? (float)$row[10] : 0,
                    'm3' => !empty($row[11]) ? (float)$row[11] : 0,
                    'harga' => !empty($row[12]) ? (float)$row[12] : 0,
                    'jumlah' => !empty($row[13]) ? (float)$row[13] : 0,
                    'status' => true,
                    'created_by' => auth()->id() ?? 1,
                    'updated_by' => auth()->id() ?? 1,
                ]);

                $countImported++;
            }

            DB::commit();

            return redirect()->back()->with('success', "Berhasil mengimport {$countImported} baris data produksi.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses file: ' . $e->getMessage());
        }
    }
}