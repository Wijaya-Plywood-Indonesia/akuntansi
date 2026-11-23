<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pembeli;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerApiController extends Controller
{
    /**
     * Memeriksa API KEY dari header X-API-KEY
     */
    protected function authenticate(Request $request): bool
    {
        $expectedKey = (string) config('services.erp.key');
        $givenKey = (string) $request->header('X-API-KEY');

        return $expectedKey !== '' && hash_equals($expectedKey, $givenKey);
    }

    /**
     * Mencari atau membuat master customer / pembeli secara langsung dari ERP.
     * POST /api/customer/resolve-or-create
     */
    public function resolveOrCreate(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid API Key',
            ], 401);
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'npwp' => 'nullable|string|max:100',
            'telepon' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $nama = trim($validated['nama']);
        if ($nama === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Nama customer tidak boleh kosong',
            ], 422);
        }

        $pembeli = Pembeli::whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($nama)])->first();

        if ($pembeli) {
            $updated = false;
            // Lengkapi jika di Akuntansi masih kosong atau user menginput data baru
            if (!empty($validated['alamat']) && (empty($pembeli->alamat) || $pembeli->alamat !== $validated['alamat'])) {
                $pembeli->alamat = $validated['alamat'];
                $updated = true;
            }
            if (!empty($validated['npwp']) && (empty($pembeli->npwp) || $pembeli->npwp !== $validated['npwp'])) {
                $pembeli->npwp = $validated['npwp'];
                $updated = true;
            }
            if (!empty($validated['telepon']) && (empty($pembeli->telepon) || $pembeli->telepon !== $validated['telepon'])) {
                $pembeli->telepon = $validated['telepon'];
                $updated = true;
            }
            if ($updated) {
                $pembeli->save();
            }
        } else {
            $pembeli = Pembeli::create([
                'nama' => $nama,
                'alamat' => $validated['alamat'] ?? null,
                'npwp' => $validated['npwp'] ?? null,
                'telepon' => $validated['telepon'] ?? null,
                'email' => $validated['email'] ?? null,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $pembeli,
        ]);
    }

    /**
     * Mendapatkan daftar nama customer untuk datalist / autocomplete di ERP.
     * GET /api/customer/list
     */
    public function list(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid API Key',
            ], 401);
        }

        $search = $request->query('q');
        $query = Pembeli::query()->select(['id', 'nama', 'alamat', 'npwp', 'telepon']);

        if ($search) {
            $query->where('nama', 'like', "%{$search}%");
        }

        $customers = $query->orderBy('nama')->limit(500)->get();

        return response()->json([
            'status' => 'success',
            'data' => $customers,
        ]);
    }
}
