<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use App\Models\ReturnPenjualan;
use App\Models\ReturnPenjualanDetail;
use App\Models\User;
use App\Services\JurnalReturnPenjualanService;
use App\Services\StokPenyesuaianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnPenjualanApiController extends Controller
{
    /**
     * Simpan transaksi retur penjualan dari ERP.
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
            'no_retur' => 'required|string|max:100',
            'no_nota' => 'required|string|max:100',
            'tanggal' => 'required|date',
            'nama_customer' => 'nullable|string|max:255',
            'alamat' => 'nullable|string',
            'metode_pembayaran' => 'nullable|string',
            'akun_pengembalian' => 'nullable|string|max:50',
            'kendaraan' => 'nullable|string|max:100',
            'plat_kendaraan' => 'nullable|string|max:100',
            'nama_sopir' => 'nullable|string|max:100',
            'sub_total' => 'nullable|numeric|min:0',
            'ppn_nominal' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'keterangan' => 'nullable|string',
            'status_return' => 'nullable|string', // DITERIMA, SELESAI, DIPROSES
            'items' => 'required|array|min:1',
            'items.*.nama_barang' => 'required|string',
            'items.*.barang_id' => 'nullable|integer',
            'items.*.penjualan_detail_id' => 'nullable|integer',
            'items.*.satuan' => 'nullable|string|max:50',
            'items.*.qty' => 'required|numeric|min:0',
            'items.*.m3' => 'nullable|numeric|min:0',
            'items.*.hitung_dari' => 'nullable|string',
            'items.*.harga_awal' => 'nullable|numeric|min:0',
            'items.*.harga_beli' => 'nullable|numeric|min:0',
            'items.*.harga_jual' => 'required|numeric|min:0',
            'items.*.potongan' => 'nullable|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
            'items.*.keterangan' => 'nullable|string',
        ]);

        try {
            $returnRecord = DB::transaction(function () use ($validated) {
                // 1. Cari nota penjualan asal
                $penjualan = Penjualan::where('no_nota', $validated['no_nota'])->first();
                if (!$penjualan) {
                    throw new \RuntimeException("Nota penjualan '{$validated['no_nota']}' belum terdaftar di Akuntansi. Silakan sinkronkan penjualan terlebih dahulu.");
                }

                // Default akun pengembalian
                $akunPengembalian = $validated['akun_pengembalian'] ?: JurnalReturnPenjualanService::getDefaultAkunPengembalian();
                $refundConfig = JurnalReturnPenjualanService::getAkunRefundConfig($akunPengembalian);
                $metode = $validated['metode_pembayaran'] ?: $refundConfig['metode'];

                $user = User::first();
                $userId = $user ? $user->id : 1;

                // 2. Cek apakah retur dengan no_retur ini sudah ada (update / idempotent)
                $returnHeader = ReturnPenjualan::where('no_retur', $validated['no_retur'])->first();

                // Siapkan data items untuk kalkulasi Buku Kitab
                $itemsForCalc = [];
                $resolvedDetails = [];

                foreach ($validated['items'] as $item) {
                    $barangId = $item['barang_id'] ?? null;
                    $barang = null;

                    if ($barangId) {
                        $barang = Barang::find($barangId);
                    }
                    if (!$barang && !empty($item['nama_barang'])) {
                        $barang = $this->resolveBarangByName($item['nama_barang']);
                        if ($barang) {
                            $barangId = $barang->id;
                        }
                    }

                    // Validasi: Hanya barang Triplek / Plywood yang boleh diretur (Veneer dilarang)
                    $namaBarangCheck = strtolower(trim(($barang?->nama_barang ?? '') ?: ($item['nama_barang'] ?? '')));
                    if (str_contains($namaBarangCheck, 'veneer')) {
                        throw new \RuntimeException("Barang '{$item['nama_barang']}' adalah jenis Veneer. Veneer tidak boleh diretur, hanya Triplek/Plywood yang diizinkan.");
                    }

                    // Cocokkan penjualan_detail_id di database Akuntansi (tabel penjualan_details)
                    // Catatan: penjualan_detail_id yang dikirim dari ERP adalah ID detail di database ERP,
                    // BUKAN ID di database Akuntansi. Jadi harus selalu divalidasi/dicari terhadap detail milik $penjualan di Akuntansi!
                    $penjualanDetailId = null;
                    if ($penjualan) {
                        $pDetail = DetailPenjualan::where('penjualan_id', $penjualan->id)
                            ->where(function ($q) use ($barangId, $item) {
                                if ($barangId) {
                                    $q->where('barang_id', $barangId);
                                } else {
                                    $q->where('nama_barang', $item['nama_barang']);
                                }
                            })
                            ->first();

                        // Jika tidak cocok via barangId/nama, fallback cari detail pertama yang belum terisi atau yang match nama mirip
                        if (!$pDetail) {
                            $pDetail = DetailPenjualan::where('penjualan_id', $penjualan->id)->first();
                        }

                        $penjualanDetailId = $pDetail?->id;
                    }

                    $hargaBeli = isset($item['harga_beli']) && (float) $item['harga_beli'] > 0
                        ? (float) $item['harga_beli']
                        : (float) ($barang?->harga_beli ?? 0);

                    $qty = (float) $item['qty'];
                    $m3 = isset($item['m3']) && $item['m3'] !== null ? (float) $item['m3'] : null;
                    $hitungDari = $item['hitung_dari'] ?? 'qty';
                    $hargaJual = (float) $item['harga_jual'];
                    $potongan = (float) ($item['potongan'] ?? 0);
                    $subtotal = (float) $item['subtotal'];

                    $itemsForCalc[] = [
                        'id_barang' => $barangId,
                        'nama_barang' => $item['nama_barang'],
                        'qty' => $qty,
                        'm3' => $m3,
                        'hitung_dari' => $hitungDari,
                        'harga_jual' => $hargaJual,
                        'harga_beli' => $hargaBeli,
                        'potongan' => $potongan,
                    ];

                    $resolvedDetails[] = [
                        'id_barang' => $barangId,
                        'penjualan_detail_id' => $penjualanDetailId,
                        'nama_barang' => $item['nama_barang'],
                        'satuan' => $item['satuan'] ?? 'pcs',
                        'harga_awal' => $item['harga_awal'] ?? $hargaJual,
                        'harga_beli' => $hargaBeli,
                        'harga_jual' => $hargaJual,
                        'potongan' => $potongan,
                        'qty' => $qty,
                        'subtotal' => $subtotal,
                        'keterangan' => $item['keterangan'] ?? null,
                    ];
                }

                // Kalkulasi Buku Kitab dan PPN
                $calc = app(JurnalReturnPenjualanService::class)->kalkulasi(
                    penjualan: $penjualan,
                    itemsRetur: $itemsForCalc,
                    akunPengembalian: $akunPengembalian,
                    excludeReturnId: $returnHeader?->id
                );

                $statusReturn = $validated['status_return'] ?? 'DITERIMA';
                $tokoId = $penjualan->toko_id ?? 1;

                $dataHeader = [
                    'penjualan_id' => $penjualan->id,
                    'no_retur' => $validated['no_retur'],
                    'no_nota' => $penjualan->no_nota,
                    'tanggal' => $validated['tanggal'] ? date('Y-m-d H:i:s', strtotime($validated['tanggal'])) : now(),
                    'nama_customer' => $validated['nama_customer'] ?? ($penjualan->nama_customer ?: 'Pelanggan'),
                    'is_member' => (bool) $penjualan->is_member,
                    'alamat' => $validated['alamat'] ?? $penjualan->alamat,
                    'metode_pembayaran' => $metode,
                    'bank' => $refundConfig['nama'],
                    'no_rekening' => $akunPengembalian,
                    'kendaraan' => $validated['kendaraan'] ?? $penjualan->kendaraan,
                    'plat_kendaraan' => $validated['plat_kendaraan'] ?? $penjualan->plat_kendaraan,
                    'nama_sopir' => $validated['nama_sopir'] ?? $penjualan->nama_sopir,
                    'sub_total' => $calc['subtotal_retur'] > 0 ? $calc['subtotal_retur'] : ($validated['sub_total'] ?? $validated['total']),
                    'ppn_nominal' => $calc['ppn_nominal'] > 0 ? $calc['ppn_nominal'] : ($validated['ppn_nominal'] ?? 0),
                    'total' => $calc['total_retur'] > 0 ? $calc['total_retur'] : $validated['total'],
                    'bayar' => $calc['total_retur'] > 0 ? $calc['total_retur'] : $validated['total'],
                    'kembalian' => 0,
                    'keterangan' => $validated['keterangan'] ?: "Retur Penjualan Ref: {$penjualan->no_nota}",
                    'status_return' => $statusReturn,
                    'jenis_retur' => $calc['jenis_retur'],
                    'kode_kitab' => $calc['kode_kitab'],
                    'akun_pengembalian' => $akunPengembalian,
                    'created_by' => $returnHeader ? $returnHeader->created_by : $userId,
                    'validate_by' => $userId,
                    'toko_id' => $tokoId,
                ];

                if ($returnHeader) {
                    $returnHeader->update($dataHeader);
                    $returnHeader->details()->delete();
                } else {
                    $returnHeader = ReturnPenjualan::create($dataHeader);
                }

                foreach ($resolvedDetails as $det) {
                    ReturnPenjualanDetail::create(array_merge($det, [
                        'id_return' => $returnHeader->id,
                    ]));
                }

                // Terbitkan Jurnal Pembantu Retur
                if (in_array($statusReturn, ['DITERIMA', 'SELESAI'])) {
                    app(JurnalReturnPenjualanService::class)->buatJurnalReturn($returnHeader, (int) $userId);
                }

                return $returnHeader;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Retur penjualan berhasil disimpan dan diproses di Akuntansi',
                'data' => [
                    'id' => $returnRecord->id,
                    'no_retur' => $returnRecord->no_retur,
                    'no_nota' => $returnRecord->no_nota,
                    'no_jurnal' => $returnRecord->fresh()->no_jurnal,
                    'total' => $returnRecord->total,
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('ReturnPenjualanApiController error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses retur penjualan di Akuntansi: ' . $e->getMessage(),
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

        // 2. Format Plywood ERP
        $isPlywoodFormat = stripos($clean, 'plywood') !== false || preg_match('/\b\d+(?:\.\d+)?\s*mm\b/i', $clean);
        if ($isPlywoodFormat) {
            preg_match('/(\d+(?:\.\d+)?)\s*mm/i', $clean, $mThick);
            $tebal = $mThick[1] ?? '';

            $kodeKayu = 'm';
            if (stripos($clean, 'sengon') !== false) {
                $kodeKayu = 's';
            }

            $grade = '';
            if (stripos($clean, 'better') !== false) {
                $grade = 'better';
            } elseif (stripos($clean, 'uty') !== false || stripos($clean, 'utility') !== false) {
                $grade = 'uty';
            } elseif (stripos($clean, 'fm') !== false) {
                $grade = 'fm';
            } elseif (preg_match('/(?:kw|grade)\s*([a-zA-Z0-9]+)/i', $clean, $mGr)) {
                $grade = strtolower($mGr[1]);
            }

            // Cek apakah ada penanda "lokal" / "local"
            $isLokal = (stripos($clean, 'lokal') !== false || stripos($clean, 'local') !== false);

            if ($tebal !== '' && $grade !== '') {
                if ($isLokal) {
                    $targetPattern = "{$tebal}{$kodeKayu} {$grade} lokal";
                    $fallbackPattern = "{$tebal}{$kodeKayu} {$grade}";
                } else {
                    $targetPattern = "{$tebal}{$kodeKayu} {$grade}";
                    $fallbackPattern = "{$tebal}{$kodeKayu} {$grade} lokal";
                }

                $foundPlywood = Barang::where('nama_barang', $targetPattern)
                    ->orWhere('nama_barang', 'like', "{$targetPattern}%")
                    ->orWhere('nama_barang', $fallbackPattern)
                    ->orWhere('nama_barang', 'like', "{$fallbackPattern}%")
                    ->orWhere('nama_barang', 'like', "{$tebal}mm%{$grade}%")
                    ->orWhere('nama_barang', 'like', "{$tebal} mm%{$grade}%")
                    ->orderByRaw("
                        CASE 
                            WHEN LOWER(nama_barang) = ? THEN 1
                            WHEN LOWER(nama_barang) LIKE ? THEN 2
                            WHEN LOWER(nama_barang) = ? THEN 3
                            WHEN LOWER(nama_barang) LIKE ? THEN 4
                            ELSE 5
                        END
                    ", [
                        strtolower($targetPattern),
                        strtolower($targetPattern) . '%',
                        strtolower($fallbackPattern),
                        strtolower($fallbackPattern) . '%',
                    ])
                    ->first();

                if ($foundPlywood) {
                    return $foundPlywood;
                }
            } elseif ($tebal !== '') {
                $foundByThick = Barang::where('nama_barang', 'like', "{$tebal}{$kodeKayu}%")
                    ->orWhere('nama_barang', 'like', "{$tebal}mm%")
                    ->first();
                if ($foundByThick) {
                    return $foundByThick;
                }
            }
        }

        // 3. Format Veneer ERP
        if (stripos($clean, 'veneer') !== false) {
            $tipe = 'Jadi';
            if (stripos($clean, 'kering') !== false) {
                $tipe = 'Kering';
            } elseif (stripos($clean, 'basah') !== false) {
                $tipe = 'Basah';
            }

            preg_match('/(\d+)\s*(?:mm)?\s*x\s*(\d+)\s*(?:mm)?\s*x\s*(\d+(?:\.\d+)?)\s*(?:mm)?/i', $clean, $mDim);
            $p = $mDim[1] ?? '';
            $l = $mDim[2] ?? '';
            $t = $mDim[3] ?? '';

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

            preg_match('/(?:kw|grade)\s*([a-zA-Z0-9]+)/i', $clean, $mGrade);
            $gradeStr = $mGrade[1] ?? '';

            $qVeneer = Barang::query()->where('nama_barang', 'like', "Veneer {$tipe}%");

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
