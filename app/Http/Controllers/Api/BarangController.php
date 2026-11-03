<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use Illuminate\Http\Request;

class BarangController extends Controller
{
    /**
     * Resolves id_barang from veneer characteristics.
     * Example input: ?jenis_veneer=Veneer Kering&bagian=Face Back&jenis_kayu=Meranti&ketebalan=0.3&kw=3
     */
    public function resolveVeneer(Request $request)
    {
        $jenisVeneer = $request->query('jenis_veneer', 'Veneer Kering');
        $bagian = $request->query('bagian'); // Face Back / Core
        $jenisKayu = $request->query('jenis_kayu');
        $ketebalan = $request->query('ketebalan');
        $ukuran = $request->query('ukuran');
        $kw = $request->query('kw');

        // Map jenis_kayu to shortcode
        $jenisKayuLower = strtolower($jenisKayu);
        $kayuCode = '';
        if (str_contains($jenisKayuLower, 'meranti')) {
            $kayuCode = 'M';
        } elseif (str_contains($jenisKayuLower, 'sengon')) {
            $kayuCode = 'S';
        } elseif (str_contains($jenisKayuLower, 'baloan')) {
            $kayuCode = 'B';
        } elseif (str_contains($jenisKayuLower, 'jabon')) {
            $kayuCode = 'J';
        } elseif (str_contains($jenisKayuLower, 'merah')) {
            $kayuCode = 'MH'; // MH was seen in DB for "MH"
        } else {
            // Default to first letter capitalized if unknown, or maybe exact?
            $kayuCode = strtoupper(substr($jenisKayu, 0, 1));
        }

        // Clean up ketebalan (e.g. 0.3 or 0.3mm to 0.3mm)
        $ketebalanNum = floatval($ketebalan);
        $ketebalanStr = $ketebalanNum . 'mm';

        // Clean up KW
        $kwStr = strtoupper(trim($kw));

        // Format: Veneer Kering Face Back S 244x122x0.5mm grade 3
        $ukuranStr = $ukuran ? $ukuran . 'x' : '';
        $namaBarang = sprintf('%s %s %s %s%s grade %s', 
            $jenisVeneer, 
            $bagian, 
            $kayuCode, 
            $ukuranStr,
            $ketebalanStr, 
            $kwStr
        );
        $namaBarang = trim(preg_replace('/\s+/', ' ', $namaBarang));

        // Find the barang
        $barang = Barang::where('nama_barang', 'like', $namaBarang)->first();

        if ($barang) {
            return response()->json([
                'success' => true,
                'nama_barang_format' => $namaBarang,
                'id_barang' => $barang->id,
                'barang' => $barang
            ]);
        }

        // Try loose search if exact match fails
        $looseName = sprintf('%s %s%%%s%%%s%%%s%%grade%%%s%%', $jenisVeneer, $bagian, $kayuCode, $ukuranStr, $ketebalanStr, $kwStr);
        $barangLoose = Barang::where('nama_barang', 'like', $looseName)->first();

        if ($barangLoose) {
            return response()->json([
                'success' => true,
                'nama_barang_format' => $namaBarang,
                'id_barang' => $barangLoose->id,
                'barang' => $barangLoose,
                'note' => 'Found via loose search'
            ]);
        }

        return response()->json([
            'success' => false,
            'nama_barang_format' => $namaBarang,
            'message' => 'Barang not found'
        ], 404);
    }
    public function resolveKayu(Request $request)
    {
        $ukuran = $request->query('ukuran') ?? $request->query('panjang');
        $jenisKayu = $request->query('jenis_kayu');

        $jenisKayuStr = ucwords(strtolower($jenisKayu));

        $namaBarang = sprintf('Persediaan Kayu %s %s', $ukuran, $jenisKayuStr);
        $namaBarang = trim(preg_replace('/\s+/', ' ', $namaBarang));

        // Find the barang
        $barang = Barang::where('nama_barang', 'like', $namaBarang)->first();

        if ($barang) {
            return response()->json([
                'success' => true,
                'nama_barang_format' => $namaBarang,
                'id_barang' => $barang->id,
                'barang' => $barang
            ]);
        }

        // Try loose search if exact match fails
        $looseName = sprintf('Persediaan Kayu%%%s%%%s%%', $ukuran, $jenisKayu);
        $barangLoose = Barang::where('nama_barang', 'like', $looseName)->first();

        if ($barangLoose) {
            return response()->json([
                'success' => true,
                'nama_barang_format' => $namaBarang,
                'id_barang' => $barangLoose->id,
                'barang' => $barangLoose,
                'note' => 'Found via loose search'
            ]);
        }

        // Fallback: master barang kayu tanpa nama jenis, mis. "persediaan kayu 130".
        $namaTanpaJenis = trim(preg_replace('/\s+/', ' ', sprintf('Persediaan Kayu %s', $ukuran)));
        $barangTanpaJenis = Barang::where('nama_barang', 'like', $namaTanpaJenis)->first();

        if ($barangTanpaJenis) {
            return response()->json([
                'success' => true,
                'nama_barang_format' => $namaBarang,
                'id_barang' => $barangTanpaJenis->id,
                'barang' => $barangTanpaJenis,
                'note' => 'Found via fallback tanpa jenis kayu'
            ]);
        }

        return response()->json([
            'success' => false,
            'nama_barang_format' => $namaBarang,
            'message' => 'Barang not found'
        ], 404);
    }

    /**
     * Resolves id_barang from plywood / platform characteristics.
     * Example input: ?nama=12m better MTH
     * or: ?tebal=12&jenis_kayu=sengon&grade=better&tipe=triplek
     */
    public function resolvePlywood(Request $request)
    {
        $rawNama = trim((string) $request->query('nama', ''));
        $tipe = strtolower(trim((string) $request->query('tipe', ''))); // 'triplek' | 'platform'
        $tebal = $request->query('tebal');
        $jenisKayu = strtolower(trim((string) $request->query('jenis_kayu', '')));
        $grade = strtolower(trim((string) $request->query('grade', '')));

        // 1. Direct match by exact / trimmed nama_barang if provided
        if ($rawNama !== '') {
            $directClean = trim(preg_replace('/\s+/', ' ', $rawNama));
            $barang = Barang::where('nama_barang', 'like', $directClean)->first();
            if ($barang) {
                return response()->json([
                    'success' => true,
                    'nama_barang_format' => $directClean,
                    'id_barang' => $barang->id,
                    'barang' => $barang,
                ]);
            }

            // If not found, try loose match
            $loose = '%' . str_replace(' ', '%', $directClean) . '%';
            $barangLoose = Barang::where('nama_barang', 'like', $loose)->first();
            if ($barangLoose) {
                return response()->json([
                    'success' => true,
                    'nama_barang_format' => $directClean,
                    'id_barang' => $barangLoose->id,
                    'barang' => $barangLoose,
                    'note' => 'Found via loose search',
                ]);
            }
        }

        // 2. Build standard pattern (e.g., "12m better MTH" or "platform 12 better MTH")
        $isPlatform = ($tipe === 'platform') || str_contains($rawNama, 'platform');
        
        $tebalNum = (float) $tebal;
        $tebalStr = ($tebalNum == (int) $tebalNum) ? (string) (int) $tebalNum : (string) $tebalNum;

        $kayuCode = '';
        if (str_contains($jenisKayu, 'meranti')) {
            $kayuCode = 'm';
        } elseif (str_contains($jenisKayu, 'sengon')) {
            $kayuCode = 's';
        } elseif (str_contains($jenisKayu, 'fm')) {
            $kayuCode = 'fm';
        }

        $pattern = ($isPlatform ? 'platform ' : '') . $tebalStr . ($kayuCode ?: '%') . ' ' . ($grade ?: '%') . ' MTH';
        $pattern = trim(preg_replace('/\s+/', ' ', $pattern));

        $barang = Barang::where('nama_barang', 'like', $pattern)->first();
        if ($barang) {
            return response()->json([
                'success' => true,
                'nama_barang_format' => $pattern,
                'id_barang' => $barang->id,
                'barang' => $barang,
            ]);
        }

        // 3. Fallback loose search
        $wildcard = ($isPlatform ? '%platform%' : '%') . $tebalStr . '%' . ($grade ?: '') . '%MTH%';
        $barangLoose = Barang::where('nama_barang', 'like', $wildcard)->first();
        if ($barangLoose) {
            return response()->json([
                'success' => true,
                'nama_barang_format' => $pattern,
                'id_barang' => $barangLoose->id,
                'barang' => $barangLoose,
                'note' => 'Found via flexible search',
            ]);
        }

        return response()->json([
            'success' => false,
            'nama_barang_format' => $rawNama ?: $pattern,
            'message' => 'Barang not found',
        ], 404);
    }

    /**
     * Resolves id_barang from bahan penolong characteristics.
     * Example input: ?nama=lem_pai&domain=whn
     * or: ?nama=Hardner&domain=wjy
     */
    public function resolvePenolong(Request $request)
    {
        $rawNama = strtolower(trim((string) $request->query('nama', '')));
        $domain = strtoupper(trim((string) $request->query('domain', ''))); // 'WHN' | 'WJY'

        if ($rawNama === '') {
            return response()->json([
                'success' => false,
                'message' => 'Parameter nama is required',
            ], 400);
        }

        // Sub anak akun ID untuk bahan penolong:
        // 197: Lem, 198: Hardner, 199: Isi Staples, 200: Pewarna, 201: Tepung, 202: Solasi Coklat, 203: Solasi Putih
        $targetSubAkun = null;
        $searchKeyword = '';

        if (str_contains($rawNama, 'pai')) {
            $targetSubAkun = 197;
            $searchKeyword = 'Lem PAI';
        } elseif (str_contains($rawNama, 'aruki')) {
            $targetSubAkun = 197;
            $searchKeyword = 'Lem Aruki';
        } elseif (str_contains($rawNama, 'dover')) {
            $targetSubAkun = 197;
            $searchKeyword = 'Lem Dover';
        } elseif (str_contains($rawNama, 'lem')) {
            $targetSubAkun = 197;
            $searchKeyword = 'Lem';
        } elseif (str_contains($rawNama, 'hardner') || str_contains($rawNama, 'hadner') || str_contains($rawNama, 'hdr')) {
            $targetSubAkun = 198;
            $searchKeyword = 'Hardner';
        } elseif (str_contains($rawNama, 'staple') || str_contains($rawNama, 'steples') || str_contains($rawNama, 'stp')) {
            $targetSubAkun = 199;
            $searchKeyword = 'Isi Staples';
        } elseif (str_contains($rawNama, 'warna') || str_contains($rawNama, 'pewarna') || str_contains($rawNama, 'pwn')) {
            $targetSubAkun = 200;
            $searchKeyword = 'Pewarna';
        } elseif (str_contains($rawNama, 'tepung') || str_contains($rawNama, 'tpg')) {
            $targetSubAkun = 201;
            $searchKeyword = 'Tepung';
        } elseif (str_contains($rawNama, 'coklat')) {
            $targetSubAkun = 202;
            $searchKeyword = 'Solasi Coklat';
        } elseif (str_contains($rawNama, 'putih')) {
            $targetSubAkun = 203;
            $searchKeyword = 'Solasi Putih';
        }

        $query = Barang::query();
        if ($targetSubAkun) {
            $query->where('id_sub_anak_akun', $targetSubAkun);
        }

        if ($domain !== '') {
            $queryDomain = (clone $query)->where(function ($q) use ($searchKeyword, $domain) {
                $q->where('nama_barang', 'like', "%{$searchKeyword}%{$domain}%")
                  ->orWhere('nama_barang', 'like', "%{$searchKeyword} %{$domain}");
            });

            $barang = $queryDomain->first();
            if ($barang) {
                return response()->json([
                    'success' => true,
                    'id_barang' => $barang->id,
                    'barang' => $barang,
                ]);
            }
        }

        // Fallback: cari by searchKeyword tanpa filter domain
        if ($searchKeyword !== '') {
            $barang = (clone $query)->where('nama_barang', 'like', "%{$searchKeyword}%")->first();
            if ($barang) {
                return response()->json([
                    'success' => true,
                    'id_barang' => $barang->id,
                    'barang' => $barang,
                ]);
            }
        }

        // Fallback terakhir: search rawNama
        $barang = Barang::where('nama_barang', 'like', "%{$rawNama}%")->first();
        if ($barang) {
            return response()->json([
                'success' => true,
                'id_barang' => $barang->id,
                'barang' => $barang,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Barang penolong not found',
        ], 404);
    }
}