<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Jurnal\RotaryJurnalReceiverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AkuntansiRotaryJurnalController extends Controller
{
    public function __construct(
        protected RotaryJurnalReceiverService $service
    ) {}

    /**
     * POST /api/jurnal/rotary/create
     * Menerima payload dari ERP dan insert ke jurnal_pembantu_headers + items
     */
    public function create(Request $request): JsonResponse
    {
        // ── 1. Auth API Key ───────────────────────────────────────────────────
        if ($request->header('X-API-KEY') !== config('services.erp.key')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // ── 2. Validasi struktur payload ──────────────────────────────────────
        $validated = $request->validate([
            'jurnal_header'                         => 'required|array',
            'jurnal_header.no_jurnal'               => 'required|string',
            'jurnal_header.tgl_transaksi'           => 'required|date',
            'jurnal_header.jenis_transaksi'         => 'required|string',
            'jurnal_header.modul_asal'              => 'required|string',
            'jurnal_header.keterangan'              => 'required|string',
            'jurnal_header.total_debit'             => 'required|numeric',
            'jurnal_header.total_kredit'            => 'required|numeric',
            'jurnal_header.is_balance'              => 'required|boolean',
            'jurnal_header.status'                  => 'required|string',

            'jurnal_items'                          => 'required|array|min:1',
            'jurnal_items.*.urut'                   => 'required|integer',
            'jurnal_items.*.map'                    => 'required|in:d,k',
            'jurnal_items.*.no_akun'                => 'required|string',
            'jurnal_items.*.nama_akun'              => 'required|string',
            'jurnal_items.*.jumlah'                 => 'required|numeric',
            'jurnal_items.*.keterangan'             => 'required|string',
            'jurnal_items.*.items'                  => 'required|array|min:1',

            'jurnal_items.*.items.*.urut'           => 'required|integer',
            'jurnal_items.*.items.*.jenis_pihak'    => 'required|string',
            'jurnal_items.*.items.*.nama_pihak'     => 'required|string',
            'jurnal_items.*.items.*.keterangan'     => 'required|string',
            'jurnal_items.*.items.*.jumlah'         => 'required|numeric',

            // field opsional di items
            'jurnal_items.*.items.*.nama_barang'     => 'nullable|string',
            'jurnal_items.*.items.*.ukuran'         => 'nullable|string',
            'jurnal_items.*.items.*.banyak'         => 'nullable|numeric',
            'jurnal_items.*.items.*.m3'             => 'nullable|numeric',
            'jurnal_items.*.items.*.harga'          => 'nullable|numeric',
            'jurnal_items.*.items.*.hit_kbk'        => 'nullable|string|in:k,b',
        ]);

        // ── 3. Cek balance ────────────────────────────────────────────────────
        if (! $validated['jurnal_header']['is_balance']) {
            Log::warning('[RotaryJurnal] Payload tidak balance', [
                'no_jurnal'    => $validated['jurnal_header']['no_jurnal'],
                'total_debit'  => $validated['jurnal_header']['total_debit'],
                'total_kredit' => $validated['jurnal_header']['total_kredit'],
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Jurnal tidak balance. Debit != Kredit.',
                'data'    => [
                    'total_debit'  => $validated['jurnal_header']['total_debit'],
                    'total_kredit' => $validated['jurnal_header']['total_kredit'],
                ],
            ], 422);
        }

        // ── 4. Cek duplikasi (no_jurnal sudah pernah dibuat) ──────────────────
        if ($this->service->isDuplicate($validated['jurnal_header']['no_jurnal'])) {
            return response()->json([
                'status'  => 'duplicate',
                'message' => 'Jurnal ' . $validated['jurnal_header']['no_jurnal'] . ' sudah pernah dibuat.',
            ], 409);
        }

        // ── 5. Proses insert ──────────────────────────────────────────────────
        try {
            $result = $this->service->store($validated);

            return response()->json([
                'status'     => 'success',
                'message'    => 'Jurnal berhasil dibuat.',
                'data'       => [
                    'no_jurnal'        => $validated['jurnal_header']['no_jurnal'],
                    'jurnal'           => $result['jurnal'],
                    'jumlah_header'    => $result['jumlah_header'],
                    'jumlah_items'     => $result['jumlah_items'],
                ],
            ], 201);

        } catch (\Throwable $e) {
            Log::error('[RotaryJurnal] Gagal insert', [
                'no_jurnal' => $validated['jurnal_header']['no_jurnal'],
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan jurnal.',
                'debug'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/jurnal/rotary/check/{no_jurnal}
     * Cek apakah jurnal sudah ada (untuk idempotency check dari ERP)
     */
    public function check(string $noJurnal): JsonResponse
    {
        $exists = $this->service->isDuplicate($noJurnal);

        return response()->json([
            'no_jurnal' => $noJurnal,
            'exists'    => $exists,
        ]);
    }
}