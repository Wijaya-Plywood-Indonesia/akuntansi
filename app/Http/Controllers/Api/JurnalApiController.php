<?php

namespace App\Http\Controllers\Api;

use App\Events\Jurnal;
use App\Http\Controllers\Controller;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class JurnalApiController extends Controller
{
    private array $mappingAkun = [
        'persediaan' => [
            130 => ['no_akun' => '115-01', 'nama_akun' => 'Persediaan Kayu 130'],
            260 => ['no_akun' => '115-02', 'nama_akun' => 'Persediaan Kayu 260'],
        ],
        'hutang_turun' => ['no_akun' => '210-021', 'nama_akun' => 'Hutang ongkos turun kayu'],
        'kas_tunai'    => ['no_akun' => '110-01',  'nama_akun' => 'Kas tunai'],
    ];

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tanggal'               => 'required|date',
            'keterangan'            => 'required|string',
            'no_dokumen'            => 'required|string',
            'seri'                  => 'required',
            'supplier'              => 'nullable|string',
            'petugas'               => 'nullable|array',
            'petugas.email'         => 'nullable|email',
            'petugas.nama'          => 'nullable|string',
            'entries'               => 'required|array|min:1',
            'entries.*.posisi'      => 'required|in:debit,kredit',
            'entries.*.total_nilai' => 'required|numeric',
            'entries.*.keterangan'  => 'nullable|string',
            'entries.*.items'       => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi payload gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Cek duplikat
        $existing = JurnalPembantuHeader::where('no_dokumen', $request->no_dokumen)->first();

        if ($existing) {
            return response()->json([
                'success'   => true,
                'no_jurnal' => $existing->jurnal,
                'message'   => 'Dokumen ini sudah pernah disimpan sebelumnya.',
                'duplicate' => true,
            ]);
        }

        // Resolve user
        $dibuatOleh = $this->resolveDibuatOleh($request->input('petugas'));

        if (! $dibuatOleh) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada user di database. Buat user terlebih dahulu.',
            ], 500);
        }

        try {
            $noJurnal = DB::transaction(function () use ($request, $dibuatOleh) {
                return $this->simpanJurnal($request->all(), $dibuatOleh);
            });

            // ── BROADCAST ke Pusher ──────────────────────────────
            // Semua user P2 yang sedang buka halaman jurnal
            // akan menerima notifikasi real-time ini
            $totalDebit = collect($request->entries)
                ->where('posisi', 'debit')
                ->sum('total_nilai');

            event(new Jurnal(
                noJurnal: $noJurnal,
                noDokumen: $request->no_dokumen,
                supplier: $request->supplier ?? '-',
                tanggal: $request->tanggal,
                totalNilai: $totalDebit,
                keterangan: $request->keterangan,
            ));
            // ─────────────────────────────────────────────────────

            return response()->json([
                'success'   => true,
                'no_jurnal' => $noJurnal,
                'message'   => "Jurnal {$noJurnal} berhasil disimpan.",
            ], 201);
        } catch (\Exception $e) {
            Log::error('[JurnalApi] Gagal simpan', [
                'no_dokumen' => $request->no_dokumen,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan jurnal: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function simpanJurnal(array $data, int $dibuatOleh): int
    {
        $noJurnal = $this->generateNoJurnal();
        $noJp     = $this->generateNoJp();

        foreach ($data['entries'] as $entry) {

            $akun = $this->resolveAkun($entry);

            $header = JurnalPembantuHeader::create([
                'no_jurnal_pembantu'  => $noJp++,
                'jurnal'              => $noJurnal,
                'tgl_transaksi'       => $data['tanggal'],
                'jenis_transaksi'     => 'bm',
                'modul_asal'          => 'kayu_masuk',
                'no_akun'             => $akun['no_akun'],
                'nama_akun'           => $akun['nama_akun'],
                'map'                 => $entry['posisi'] === 'debit' ? 'd' : 'k',
                'keterangan'          => $entry['keterangan'] ?? $data['keterangan'],
                'no_dokumen'          => $data['no_dokumen'],
                'total_nilai'         => $entry['total_nilai'],
                'status'              => JurnalPembantuHeader::STATUS_DRAFT,
                'adalah_jurnal_balik' => false,
                'dibuat_oleh'         => $dibuatOleh,
                'diubah_oleh'         => null,
                'diposting_oleh'      => null,
            ]);

            foreach ($entry['items'] as $i => $item) {
                JurnalPembantuItem::create([
                    'jurnal_pembantu_header_id' => $header->id,
                    'urut'        => $item['urut'] ?? ($i + 1),
                    'nama'        => $item['nama_barang'] ?? $item['nama'] ?? '-',
                    'jenis_pihak' => 'pemasok',
                    'no_dokumen'  => $data['no_dokumen'],
                    'keterangan'  => $this->buildKeterangan($item, $data),
                    'ukuran'      => $item['ukuran']  ?? null,
                    'kualitas'    => $item['grade']   ?? $item['kode_lahan'] ?? null,
                    'banyak'      => $item['banyak']  ?? null,
                    'm3'          => $item['m3']       ?? null,
                    'harga'       => $item['harga']    ?? 0,
                    'hit_kbk'     => $this->resolveHitKbk($entry, $item),
                    'status'      => true,
                    'created_by'  => $dibuatOleh,
                    'updated_by'  => null,
                ]);
            }
        }

        return $noJurnal;
    }

    private function resolveAkun(array $entry): array
    {
        if ($entry['posisi'] === 'debit') {
            $panjang = (int) ($entry['panjang'] ?? 0);
            $akun    = $this->mappingAkun['persediaan'][$panjang] ?? null;

            if (! $akun) {
                throw new \RuntimeException(
                    "Mapping akun tidak ditemukan untuk kayu panjang {$panjang}cm."
                );
            }

            return $akun;
        }

        return match ($entry['jenis'] ?? '') {
            'hutang_turun' => $this->mappingAkun['hutang_turun'],
            'kas_tunai'    => $this->mappingAkun['kas_tunai'],
            default        => throw new \RuntimeException(
                "Jenis kredit tidak dikenal: '{$entry['jenis']}'"
            ),
        };
    }

    private function resolveDibuatOleh(?array $petugas): ?int
    {
        if (! empty($petugas['email'])) {
            $userId = User::where('email', $petugas['email'])->value('id');
            if ($userId) return $userId;
        }

        return User::orderBy('id')->value('id');
    }

    private function resolveHitKbk(array $entry, array $item): ?string
    {
        if (
            $entry['posisi'] === 'debit'
            && isset($item['m3'])
            && (float) $item['m3'] > 0
        ) {
            return 'k';
        }

        return null;
    }

    private function buildKeterangan(array $item, array $data): string
    {
        $parts = [];

        if (! empty($item['grade']))     $parts[] = "Grade: {$item['grade']}";
        if (! empty($item['ukuran']))    $parts[] = $item['ukuran'];
        if (! empty($item['keterangan'])) $parts[] = $item['keterangan'];

        $parts[] = "Seri {$data['seri']}";

        if (! empty($data['supplier'])) $parts[] = $data['supplier'];

        return implode(' | ', array_filter($parts));
    }

    private function generateNoJurnal(): int
    {
        return (JurnalPembantuHeader::max('jurnal') ?? 0) + 1;
    }

    private function generateNoJp(): int
    {
        return (JurnalPembantuHeader::max('no_jurnal_pembantu') ?? 0) + 1;
    }
}
