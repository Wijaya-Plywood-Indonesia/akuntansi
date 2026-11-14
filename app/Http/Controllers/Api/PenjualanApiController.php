<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\DetailPenjualan;
use App\Models\Pembeli;
use App\Models\Penjualan;
use App\Models\RekeningPerusahaan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PenjualanApiController extends Controller
{
    /**
     * Store atau update penjualan dari ERP.
     * Header: X-API-KEY
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Autentikasi API Key
        $expectedKey = (string) config('services.erp.key');
        $givenKey = (string) $request->header('X-API-KEY');

        if ($expectedKey === '' || !hash_equals($expectedKey, $givenKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid API Key',
            ], 401);
        }

        // 2. Validasi input
        $validated = $request->validate([
            'no_nota' => 'required|string|max:100',
            'tanggal' => 'required|date',
            'nama_customer' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string|max:50',
            'metode_pembayaran' => 'required|string', // TUNAI, TRANSFER, COD, etc
            'jenis_transaksi' => 'nullable|string',   // BAYAR_DIMUKA, COD, DP
            'bank' => 'nullable|string|max:100',
            'no_rekening' => 'nullable|string|max:100',
            'kendaraan' => 'nullable|string|max:100',
            'plat_kendaraan' => 'nullable|string|max:100',
            'nama_sopir' => 'nullable|string|max:100',
            'sub_total' => 'nullable|numeric|min:0',
            'ppn_persen' => 'nullable|numeric|min:0',
            'ppn_nominal' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'bayar' => 'nullable|numeric|min:0',
            'kembalian' => 'nullable|numeric|min:0',
            'keterangan' => 'nullable|string',
            'keterangan_pembayaran' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.nama_barang' => 'required|string',
            'items.*.barang_id' => 'nullable|integer',
            'items.*.satuan' => 'nullable|string|max:50',
            'items.*.qty' => 'required|numeric|min:0',
            'items.*.m3' => 'nullable|numeric|min:0',
            'items.*.hitung_dari' => 'nullable|string', // qty | kubikasi
            'items.*.harga_awal' => 'nullable|numeric|min:0',
            'items.*.harga_jual' => 'required|numeric|min:0',
            'items.*.potongan' => 'nullable|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string',
        ]);

        try {
            $penjualan = DB::transaction(function () use ($validated) {
                // Cari atau buat master customer / pembeli
                $pembeli = Pembeli::firstOrCreate(
                    ['nama' => trim($validated['nama_customer'])],
                    [
                        'alamat' => $validated['alamat'] ?? null,
                        'telepon' => $validated['telepon'] ?? null,
                    ]
                );

                // Rekening check jika transfer
                $bank = $validated['bank'] ?? null;
                $noRekening = $validated['no_rekening'] ?? null;
                if (!$bank && $noRekening) {
                    $rek = RekeningPerusahaan::where('no_rekening', $noRekening)->first();
                    $bank = $rek?->nama_bank;
                }

                $user = User::first();
                $userId = $user ? $user->id : 1;

                // Cek apakah penjualan dengan no_nota ini sudah ada (idempotent / update)
                $penjualan = Penjualan::where('no_nota', $validated['no_nota'])->first();

                $metode = strtoupper($validated['metode_pembayaran']);
                if (str_contains($metode, 'TRANSFER')) {
                    $metodeBayar = 'TRANSFER';
                } elseif (str_contains($metode, 'TUNAI')) {
                    $metodeBayar = 'TUNAI';
                } else {
                    $metodeBayar = 'TUNAI';
                }

                $dataHeader = [
                    'no_nota' => $validated['no_nota'],
                    'tanggal' => $validated['tanggal'],
                    'nama_customer' => $validated['nama_customer'],
                    'pembeli_id' => $pembeli->id,
                    'is_member' => false,
                    'alamat' => $validated['alamat'] ?? null,
                    'metode_pembayaran' => $metodeBayar,
                    'jenis_transaksi' => $validated['jenis_transaksi'] ?? 'BAYAR_DIMUKA',
                    'bank' => $bank,
                    'no_rekening' => $noRekening,
                    'kendaraan' => $validated['kendaraan'] ?? null,
                    'plat_kendaraan' => $validated['plat_kendaraan'] ?? null,
                    'nama_sopir' => $validated['nama_sopir'] ?? null,
                    'sub_total' => $validated['sub_total'] ?? $validated['total'],
                    'ppn_persen' => $validated['ppn_persen'] ?? 0,
                    'ppn_nominal' => $validated['ppn_nominal'] ?? 0,
                    'total' => $validated['total'],
                    'bayar' => $validated['bayar'] ?? 0,
                    'kembalian' => $validated['kembalian'] ?? 0,
                    'keterangan' => $validated['keterangan'] ?? null,
                    'keterangan_pembayaran' => $validated['keterangan_pembayaran'] ?? null,
                    'status_transaksi' => $validated['status_transaksi'] ?? null,
                    'user_id' => $penjualan ? $penjualan->user_id : $userId,
                    'validated_by' => null,
                ];

                if ($penjualan) {
                    $penjualan->update($dataHeader);
                    // Hapus detail lama untuk digantikan detail baru
                    $penjualan->details()->delete();
                } else {
                    $penjualan = Penjualan::create($dataHeader);
                }

                // Simpan item detail
                foreach ($validated['items'] as $item) {
                    $barangId = $item['barang_id'] ?? null;

                    // Jika barang_id belum ada, cari via resolver barang
                    if (!$barangId && !empty($item['nama_barang'])) {
                        $barang = $this->resolveBarangByName($item['nama_barang']);
                        if ($barang) {
                            $barangId = $barang->id;
                        }
                    }

                    $hitungDari = $item['hitung_dari'] ?? (!empty($item['m3']) ? 'kubikasi' : 'qty');

                    DetailPenjualan::create([
                        'penjualan_id' => $penjualan->id,
                        'barang_id' => $barangId,
                        'nama_barang' => $item['nama_barang'],
                        'satuan' => $item['satuan'] ?? 'pcs',
                        'qty' => $item['qty'],
                        'kubikasi' => $item['m3'] ?? 0,
                        'hitung_dari' => $hitungDari,
                        'harga_awal' => $item['harga_awal'] ?? $item['harga_jual'],
                        'harga_jual' => $item['harga_jual'],
                        'potongan' => $item['potongan'] ?? 0,
                        'subtotal' => $item['subtotal'],
                        'keterangan' => $item['keterangan'] ?? null,
                    ]);
                }

                return $penjualan;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Penjualan berhasil disimpan di web Akuntansi',
                'data' => [
                    'id' => $penjualan->id,
                    'no_nota' => $penjualan->no_nota,
                    'total' => $penjualan->total,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('PenjualanApiController error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan penjualan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper untuk menyelesaikan nama_barang ke Model Barang.
     */
    protected function resolveBarangByName(string $rawNama): ?Barang
    {
        $clean = trim(preg_replace('/\s+/', ' ', $rawNama));
        if ($clean === '') {
            return null;
        }

        // 1. Exact match
        $barang = Barang::where('nama_barang', $clean)->first();
        if ($barang) {
            return $barang;
        }

        // 2. Jika nama barang dari format Plywood ERP (contoh: "Plywood - 18 mm - Meranti - KW BETTER")
        if (stripos($clean, 'plywood') !== false) {
            preg_match('/(\d+(?:\.\d+)?)\s*mm/i', $clean, $mThick);
            $tebal = $mThick[1] ?? '';

            $kodeKayu = 'm'; // default meranti
            if (stripos($clean, 'sengon') !== false) {
                $kodeKayu = 's';
            }

            $grade = '';
            if (stripos($clean, 'better') !== false) {
                $grade = 'better';
            } elseif (stripos($clean, 'uty') !== false) {
                $grade = 'uty';
            } elseif (stripos($clean, 'fm') !== false) {
                $grade = 'fm';
            }

            if ($tebal !== '' && $grade !== '') {
                $targetPattern = "{$tebal}{$kodeKayu} {$grade}";
                // Prioritaskan exact match "18m better" sebelum "18m better lokal"
                $foundPlywood = Barang::where('nama_barang', $targetPattern)
                    ->orWhere('nama_barang', 'like', "{$targetPattern}%")
                    ->orderByRaw("CASE WHEN LOWER(nama_barang) = ? THEN 1 ELSE 2 END", [strtolower($targetPattern)])
                    ->first();

                if ($foundPlywood) {
                    return $foundPlywood;
                }
            }
        }

        // 3. Jika nama barang dari format Veneer ERP (contoh: "Veneer Jadi - 244mm x 122mm x 0.5mm - Sengon - KW 1")
        if (stripos($clean, 'veneer') !== false) {
            $tipe = 'Jadi';
            if (stripos($clean, 'kering') !== false) {
                $tipe = 'Kering';
            } elseif (stripos($clean, 'basah') !== false) {
                $tipe = 'Basah';
            }

            // Ekstrak dimensi (panjang x lebar x tebal)
            preg_match('/(\d+)\s*(?:mm)?\s*x\s*(\d+)\s*(?:mm)?\s*x\s*(\d+(?:\.\d+)?)\s*(?:mm)?/i', $clean, $mDim);
            $p = $mDim[1] ?? '';
            $l = $mDim[2] ?? '';
            $t = $mDim[3] ?? '';

            // Kode Jenis Kayu di Akuntansi: S = Sengon, M = Meranti, WR = Waru, MH = Mahoni, J = Jabon
            $kodeKayu = 'S';
            if (stripos($clean, 'meranti') !== false) {
                $kodeKayu = 'M';
            } elseif (stripos($clean, 'waru') !== false) {
                $kodeKayu = 'WR';
            } elseif (stripos($clean, 'mahoni') !== false) {
                $kodeKayu = 'MH';
            } elseif (stripos($clean, 'jabon') !== false) {
                $kodeKayu = 'J';
            }

            // Ekstrak Grade: KW 1 -> grade 1, KW 2 -> grade 2, Grade A -> grade A / grade 1, dst.
            preg_match('/(?:kw|grade)\s*([a-zA-Z0-9]+)/i', $clean, $mGrade);
            $gradeStr = $mGrade[1] ?? '';

            $qVeneer = Barang::query()
                ->where('nama_barang', 'like', "Veneer {$tipe}%");

            if ($p !== '' && $l !== '' && $t !== '') {
                $dimPattern = "{$p}x{$l}x{$t}mm";
                $qVeneer->where('nama_barang', 'like', "%{$dimPattern}%");
            }

            if ($kodeKayu !== '') {
                $qVeneer->where('nama_barang', 'like', "% {$kodeKayu} %");
            }

            if ($gradeStr !== '') {
                $qVeneer->where(function ($sub) use ($gradeStr) {
                    $sub->where('nama_barang', 'like', "%grade {$gradeStr}%")
                        ->orWhere('nama_barang', 'like', "% {$gradeStr}%");
                });
            }

            $foundVeneer = $qVeneer->first();
            if ($foundVeneer) {
                return $foundVeneer;
            }

            // Fallback veneer jika dimensi tidak full cocok: cari dengan kata kunci fleksibel
            $fallbackVeneer = Barang::where('nama_barang', 'like', "Veneer {$tipe}%")
                ->when($t !== '', fn ($q) => $q->where('nama_barang', 'like', "%{$t}mm%"))
                ->when($gradeStr !== '', fn ($q) => $q->where('nama_barang', 'like', "%{$gradeStr}%"))
                ->first();

            if ($fallbackVeneer) {
                return $fallbackVeneer;
            }
        }

        // 4. Like match biasa
        $barang = Barang::where('nama_barang', 'like', $clean)->first();
        if ($barang) {
            return $barang;
        }

        // 5. Loose word matching
        $loose = '%' . str_replace(' ', '%', $clean) . '%';
        $barang = Barang::where('nama_barang', 'like', $loose)->first();
        if ($barang) {
            return $barang;
        }

        // 6. Substring fallback
        return Barang::where('nama_barang', 'like', "%{$clean}%")->first();
    }
}
