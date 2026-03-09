<?php

namespace App\Http\Controllers\Api;

use App\Events\Jurnal;
use App\Events\JurnalBaru;
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

    // ----------------------------------------------------------
    // STORE — POST /api/jurnal/store
    // ----------------------------------------------------------
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

            // ── Broadcast ke Pusher ──────────────────────────────
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

    // ----------------------------------------------------------
    // SIMPAN JURNAL
    // ----------------------------------------------------------
    private function simpanJurnal(array $data, int $dibuatOleh): int
    {
        $noJurnal = $this->generateNoJurnal();
        $noJp     = $this->generateNoJp();
        $supplier = $data['supplier'] ?? '-';

        foreach ($data['entries'] as $entry) {

            $akun = $this->resolveAkun($entry);

            // ── Buat Header ────────────────────────────────────
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

            // ── Rekap Items ─────────────────────────────────────
            // Gabungkan item yang memiliki kombinasi sama:
            // nama_barang + grade + ukuran + harga
            // → banyak, m3, jumlah dijumlahkan
            $items = $this->rekapItems($entry['items']);

            // ── Buat Items ─────────────────────────────────────
            foreach ($items as $i => $item) {
                JurnalPembantuItem::create([
                    'jurnal_pembantu_header_id' => $header->id,
                    'urut'        => $item['urut'] ?? ($i + 1),

                    // Isi kolom 'nama' dan 'nama_barang' sekaligus
                    'nama'        => $item['nama_barang'] ?? $item['nama'] ?? '-',
                    'nama_barang' => $item['nama_barang'] ?? $item['nama'] ?? '-',

                    // jenis_pihak = kategori, nama_pihak = nama supplier dari P1
                    'jenis_pihak' => 'pemasok',
                    'nama_pihak'  => $supplier,

                    'no_dokumen'  => $data['no_dokumen'],
                    'keterangan'  => $this->buildKeterangan($item, $data),

                    'ukuran'      => $item['ukuran']  ?? null,
                    'kualitas'    => $item['grade']   ?? $item['kode_lahan'] ?? null,

                    'banyak'      => $item['banyak']  ?? null,
                    'm3'          => $item['m3']       ?? null,
                    'harga'       => $item['harga']    ?? 0,

                    // Jumlah dihitung otomatis
                    'jumlah'      => $this->hitungJumlah($item),

                    'hit_kbk'     => $this->resolveHitKbk($entry, $item),
                    'status'      => true,
                    'created_by'  => $dibuatOleh,
                    'updated_by'  => null,
                ]);
            }
        }

        return $noJurnal;
    }

    // ----------------------------------------------------------
    // REKAP ITEMS
    // Gabungkan item yang memiliki kombinasi sama:
    //   nama_barang + grade + ukuran + harga
    //
    // Contoh: ada 3 baris "Sengon (B) | 130cm | D: 17-17 | harga 750"
    // → digabung jadi 1 baris dengan banyak=3, m3 dijumlah, jumlah dijumlah
    // ----------------------------------------------------------
    private function rekapItems(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            // Kunci unik berdasarkan kombinasi ini
            $key = implode('|', [
                $item['nama_barang'] ?? $item['nama'] ?? '-',
                $item['grade']       ?? '',
                $item['ukuran']      ?? '',
                $item['harga']       ?? 0,
            ]);

            if (! isset($grouped[$key])) {
                // Pertama kali muncul — simpan apa adanya
                $grouped[$key] = $item;
            } else {
                // Sudah ada — jumlahkan banyak, m3, jumlah
                $grouped[$key]['banyak'] = (float) ($grouped[$key]['banyak'] ?? 0)
                    + (float) ($item['banyak'] ?? 0);

                $grouped[$key]['m3']     = round(
                    (float) ($grouped[$key]['m3'] ?? 0) + (float) ($item['m3'] ?? 0),
                    6
                );

                $grouped[$key]['jumlah'] = (float) ($grouped[$key]['jumlah'] ?? 0)
                    + (float) ($item['jumlah'] ?? 0);
            }
        }

        // Reset urut setelah direkap
        $result = array_values($grouped);
        foreach ($result as $i => &$item) {
            $item['urut'] = $i + 1;
        }

        return $result;
    }

    // ----------------------------------------------------------
    // HITUNG JUMLAH
    // Prioritas: ambil dari payload jika ada
    // Fallback : hitung dari harga × m3 atau harga × banyak
    // ----------------------------------------------------------
    private function hitungJumlah(array $item): float
    {
        // Jika payload P1 sudah kirim jumlah, pakai langsung
        if (isset($item['jumlah']) && (float) $item['jumlah'] != 0) {
            return (float) $item['jumlah'];
        }

        $harga  = (float) ($item['harga']  ?? 0);
        $m3     = (float) ($item['m3']     ?? 0);
        $banyak = (float) ($item['banyak'] ?? 0);

        if ($m3 > 0)     return $harga * $m3;     // hit_kbk = 'k'
        if ($banyak > 0) return $harga * $banyak; // hit_kbk = 'b'

        return $harga; // nilai langsung
    }

    // ----------------------------------------------------------
    // RESOLVE AKUN
    // ----------------------------------------------------------
    private function resolveAkun(array $entry): array
    {
        if ($entry['posisi'] === 'debit') {
            $panjang = (int) ($entry['panjang'] ?? 0);
            $akun    = $this->mappingAkun['persediaan'][$panjang] ?? null;

            if (! $akun) {
                throw new \RuntimeException(
                    "Mapping akun tidak ditemukan untuk kayu panjang {$panjang}cm. " .
                        "Tambahkan di \$mappingAkun['persediaan'][{$panjang}]."
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

    // ----------------------------------------------------------
    // RESOLVE DIBUAT OLEH
    // ----------------------------------------------------------
    private function resolveDibuatOleh(?array $petugas): ?int
    {
        if (! empty($petugas['email'])) {
            $userId = User::where('email', $petugas['email'])->value('id');
            if ($userId) return $userId;
        }

        return User::orderBy('id')->value('id');
    }

    // ----------------------------------------------------------
    // RESOLVE HIT_KBK
    // ----------------------------------------------------------
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

    // ----------------------------------------------------------
    // BUILD KETERANGAN ITEM
    // ----------------------------------------------------------
    private function buildKeterangan(array $item, array $data): string
    {
        $parts = [];

        if (! empty($item['grade']))      $parts[] = "Grade: {$item['grade']}";
        if (! empty($item['ukuran']))     $parts[] = $item['ukuran'];
        if (! empty($item['keterangan'])) $parts[] = $item['keterangan'];

        $parts[] = "Seri {$data['seri']}";

        if (! empty($data['supplier'])) $parts[] = $data['supplier'];

        return implode(' | ', array_filter($parts));
    }

    // ----------------------------------------------------------
    // GENERATE NO JURNAL
    // ----------------------------------------------------------
    private function generateNoJurnal(): int
    {
        return (JurnalPembantuHeader::max('jurnal') ?? 0) + 1;
    }

    // ----------------------------------------------------------
    // GENERATE NO JURNAL PEMBANTU
    // ----------------------------------------------------------
    private function generateNoJp(): int
    {
        return (JurnalPembantuHeader::max('no_jurnal_pembantu') ?? 0) + 1;
    }
}
