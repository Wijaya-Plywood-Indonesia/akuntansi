<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ImportJurnalProduksiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * API generik penerima jurnal produksi dari ERP (semua jenis produksi:
 * rotary, kedi, dryer, join, repair, hotpress, dst).
 *
 * POST /api/jurnal/produksi
 * Header: X-API-KEY (= ERP_API_KEY di .env akuntansi)
 *
 * {
 *   "jenis": "dryer",
 *   "tanggal": "2026-10-14",
 *   "dikirim_oleh": { "nama": "Nia", "email": "nia@contoh.com" },  // opsional
 *   "jurnals": [
 *     {
 *       "no_dokumen": "DRY/20261014/PAGI",          // kunci idempotensi
 *       "rows": [
 *         { "no_akun": "1402.3", "nama_akun": "...", "tgl": "2026-10-14",
 *           "map": "d", "nama": "dryer pagi", "keterangan": "...",
 *           "hit_kbk": "m", "banyak": 659, "m3": 0.9809, "harga": 8000000,
 *           "total": 7847200, "id_barang": 123, "no": "200" }
 *       ]
 *     }
 *   ]
 * }
 *
 * Perilaku per jurnal (no_dokumen):
 *   - belum ada                 -> dibuat
 *   - ada, semua baris draft    -> ditimpa (diperbarui)
 *   - ada, sudah diposting dll  -> ditolak (tidak diubah)
 *
 * jenis "kayu_masuk" disimpan sebagai pembelian (modul_asal 'pembelian_kayu'),
 * jenis lain sebagai produksi.
 */
class JurnalProduksiApiController extends Controller
{
    public function __construct(private readonly ImportJurnalProduksiService $service) {}

    public function store(Request $request): JsonResponse
    {
        // ── 1. Autentikasi API key ───────────────────────────────────────────
        $key = (string) config('services.erp.key');
        $given = (string) $request->header('X-API-KEY');

        if ($key === '' || ! hash_equals($key, $given)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        // ── 2. Validasi struktur ─────────────────────────────────────────────
        $data = $request->validate([
            'jenis' => 'required|string|max:50',
            'tanggal' => 'required|date',
            'dikirim_oleh' => 'nullable|array',
            'dikirim_oleh.nama' => 'nullable|string|max:255',
            'dikirim_oleh.email' => 'nullable|string|max:255',
            'jurnals' => 'required|array|min:1',
            'jurnals.*.no_dokumen' => 'required|string|max:100|distinct',
            'jurnals.*.rows' => 'required|array|min:1',
            'jurnals.*.rows.*.no_akun' => 'required|string|max:30',
            'jurnals.*.rows.*.nama_akun' => 'required|string|max:255',
            'jurnals.*.rows.*.map' => 'required|in:d,k',
            'jurnals.*.rows.*.tgl' => 'nullable|date',
            'jurnals.*.rows.*.no' => 'nullable|string|max:50',
            'jurnals.*.rows.*.nama' => 'nullable|string|max:255',
            'jurnals.*.rows.*.keterangan' => 'nullable|string|max:500',
            'jurnals.*.rows.*.hit_kbk' => 'nullable|in:m,b',
            'jurnals.*.rows.*.banyak' => 'nullable|numeric',
            'jurnals.*.rows.*.m3' => 'nullable|numeric',
            'jurnals.*.rows.*.harga' => 'nullable|numeric',
            'jurnals.*.rows.*.total' => 'required|numeric',
            'jurnals.*.rows.*.id_barang' => 'nullable|integer',
        ]);

        // ── 3. Jurnal harus balance (cek semua dulu, tolak seluruh request) ──
        $tidakBalance = [];
        foreach ($data['jurnals'] as $j) {
            $debit = collect($j['rows'])->where('map', 'd')->sum('total');
            $kredit = collect($j['rows'])->where('map', 'k')->sum('total');

            if (abs($debit - $kredit) > 1) {
                $tidakBalance[$j['no_dokumen']] = ['debit' => $debit, 'kredit' => $kredit];
            }
        }

        if ($tidakBalance) {
            Log::warning('[JurnalProduksiApi] Tidak balance', ['jenis' => $data['jenis'], 'detail' => $tidakBalance]);

            return response()->json([
                'status' => 'error',
                'message' => 'Ada jurnal yang tidak balance (debit != kredit). Tidak ada yang disimpan.',
                'tidak_balance' => $tidakBalance,
            ], 422);
        }

        // ── 4. Petakan ke struktur internal importer ─────────────────────────
        $jurnals = [];
        foreach ($data['jurnals'] as $j) {
            $items = [];
            foreach ($j['rows'] as $r) {
                $items[] = [
                    'nama_akun' => trim($r['nama_akun']),
                    'tgl' => $r['tgl'] ?? $data['tanggal'],
                    'no_akun' => trim($r['no_akun']),
                    'no' => trim((string) ($r['no'] ?? '')), // mis. no seri kayu masuk
                    'nama' => trim((string) ($r['nama'] ?? '')),
                    'keterangan' => trim((string) ($r['keterangan'] ?? '')),
                    'map' => $r['map'],
                    'hit_kbk' => $r['hit_kbk'] ?? null,
                    'banyak' => isset($r['banyak']) ? (float) $r['banyak'] : null,
                    'm3' => isset($r['m3']) ? (float) $r['m3'] : null,
                    'harga' => (float) ($r['harga'] ?? 0),
                    'total' => (float) $r['total'],
                    // id_barang kosong => dikosongkan; angka => barangs.id
                    'barang_identifier' => isset($r['id_barang']) ? (string) $r['id_barang'] : '',
                ];
            }

            $jurnals[] = ['no_dokumen' => $j['no_dokumen'], 'items' => $items];
        }

        // ── 5. Simpan ────────────────────────────────────────────────────────
        // Pencatat jurnal = user pengirim di ERP (dicocokkan lewat email, lalu nama).
        // Kalau tidak ketemu, dipakai user default (ERP_API_USER_ID) + peringatan.
        [$userId, $userNama, $userCocok] = $this->resolveUser($data['dikirim_oleh'] ?? null);

        try {
            // Kayu masuk = pembelian, bukan produksi: modul tersendiri supaya tab-nya terpisah.
            $modulAsal = $data['jenis'] === 'kayu_masuk' ? 'pembelian_kayu' : 'produksi';

            $hasil = $this->service->importDariApi($jurnals, $userId, $modulAsal);
        } catch (\Throwable $e) {
            Log::error('[JurnalProduksiApi] Gagal simpan', [
                'jenis' => $data['jenis'],
                'tanggal' => $data['tanggal'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan jurnal. Tidak ada yang disimpan.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        $dokumen = collect($hasil['dokumen']);
        $berhasil = $dokumen->whereIn('status', ['dibuat', 'diperbarui'])->count();

        if (! $userCocok && $berhasil > 0) {
            $asal = trim((string) ($data['dikirim_oleh']['nama'] ?? $data['dikirim_oleh']['email'] ?? ''));
            $hasil['warnings'][] = ($asal !== '' ? "Pengirim '{$asal}'" : 'Pengirim')
                ." tidak ditemukan di user akuntansi (dicocokkan lewat email/nama), jurnal dicatat atas user id {$userId}.";
        }

        Log::info('[JurnalProduksiApi] Selesai', [
            'jenis' => $data['jenis'],
            'tanggal' => $data['tanggal'],
            'dokumen' => $hasil['dokumen'],
        ]);

        return response()->json([
            'status' => $berhasil === 0 ? 'rejected' : ($berhasil < $dokumen->count() ? 'partial' : 'success'),
            'dokumen' => $hasil['dokumen'],
            'dibuat_oleh' => ['id' => $userId, 'nama' => $userNama, 'cocok' => $userCocok],
            'errors' => $hasil['errors'],
            'warnings' => $hasil['warnings'],
        ], $berhasil === 0 ? 409 : 200);
    }

    /**
     * Cari user akuntansi untuk pengirim dari ERP.
     * Urutan: email (persis, tanpa beda huruf besar/kecil) -> nama (hanya jika tepat 1 yang cocok)
     * -> user default dari config.
     *
     * @return array{0:int, 1:?string, 2:bool} [id user, nama user, apakah cocok]
     */
    private function resolveUser(?array $pengirim): array
    {
        $email = strtolower(trim((string) ($pengirim['email'] ?? '')));
        $nama = strtolower(trim((string) ($pengirim['nama'] ?? '')));

        if ($email !== '') {
            $u = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($u) {
                return [(int) $u->id, $u->name, true];
            }
        }

        if ($nama !== '') {
            $kandidat = User::whereRaw('LOWER(name) = ?', [$nama])->limit(2)->get();
            if ($kandidat->count() === 1) {
                return [(int) $kandidat->first()->id, $kandidat->first()->name, true];
            }
        }

        $default = (int) config('services.erp.user_id', 1);

        return [$default, User::whereKey($default)->value('name'), false];
    }
}