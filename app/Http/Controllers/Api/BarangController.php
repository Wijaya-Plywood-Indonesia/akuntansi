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
}
