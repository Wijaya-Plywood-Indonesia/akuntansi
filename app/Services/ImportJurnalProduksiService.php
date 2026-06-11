<?php

namespace App\Services;

use App\Models\AnakAkun;
use App\Models\IndukAkun;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\SubAnakAkun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Service Import Jurnal Produksi dari file Excel (.xlsx)
 *
 * Format Excel yang diterima (sheet "jurnal produksi"):
 * - Baris header jurnal : "No. Jurnal: ROT/YYYYMMDD/MESIN"
 * - Baris header kolom  : Nama Akun | tgl | jurnal | No Akun | No | mm | Nama | Keterangan | map | hit kbk | Banyak | M3 | Harga | Total
 * - Baris data          : isi data jurnal
 * - Baris kosong        : pemisah antar jurnal
 */
class ImportJurnalProduksiService
{
    private array $akunCache = [];
    private array $errors    = [];
    private array $results   = [];

    public function import(string $filePath, int $userId): array
    {
        $this->errors  = [];
        $this->results = [];

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors'  => ['Gagal membaca file: ' . $e->getMessage()],
                'results' => [],
            ];
        }

        // Cari sheet "jurnal produksi" (case-insensitive)
        $sheet = null;
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (strtolower(trim($name)) === 'jurnal produksi') {
                $sheet = $spreadsheet->getSheetByName($name);
                break;
            }
        }

        if (!$sheet) {
            return [
                'success' => false,
                'errors'  => ['Sheet "jurnal produksi" tidak ditemukan di file Excel.'],
                'results' => [],
            ];
        }

        // toArray(nullValue, calculateFormulas, formatData, returnCellRef)
        // calculateFormulas=true agar nilai formula terbaca
        // formatData=false agar angka tidak diformat sebagai string
        $rows    = $sheet->toArray(null, true, false, false);
        $jurnals = $this->parseJurnals($rows);

        if (empty($jurnals)) {
            return [
                'success' => false,
                'errors'  => ['Tidak ada data jurnal valid yang ditemukan di sheet "jurnal produksi".'],
                'results' => [],
            ];
        }

        DB::transaction(function () use ($jurnals, $userId) {
            foreach ($jurnals as $jurnal) {
                $this->simpanJurnal($jurnal, $userId);
            }
        });

        return [
            'success' => empty($this->errors),
            'errors'  => $this->errors,
            'results' => $this->results,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // PARSER: Baca baris Excel dan kelompokkan per jurnal
    // ══════════════════════════════════════════════════════════════

    private function parseJurnals(array $rows): array
    {
        $jurnals       = [];
        $currentJurnal = null;
        $isDataRow     = false;

        foreach ($rows as $row) {
            $col0 = trim((string) ($row[0] ?? ''));

            // Deteksi baris header jurnal: "No. Jurnal: XXX"
            if (str_starts_with($col0, 'No. Jurnal:')) {
                if ($currentJurnal && !empty($currentJurnal['items'])) {
                    $jurnals[] = $currentJurnal;
                }
                $noDokumen     = trim(str_replace('No. Jurnal:', '', $col0));
                $currentJurnal = ['no_dokumen' => $noDokumen, 'items' => []];
                $isDataRow     = false;
                continue;
            }

            // Deteksi baris header kolom: "Nama Akun"
            if ($col0 === 'Nama Akun') {
                $isDataRow = true;
                continue;
            }

            if (!$isDataRow || !$currentJurnal || empty($col0)) {
                continue;
            }

            // Mapping kolom:
            // 0=Nama Akun, 1=tgl, 2=jurnal, 3=No Akun, 4=No, 5=mm,
            // 6=Nama, 7=Keterangan, 8=map, 9=hit kbk, 10=Banyak, 11=M3, 12=Harga, 13=Total
            $noAkun = trim((string) ($row[3] ?? ''));
            $map    = strtolower(trim((string) ($row[8] ?? '')));

            if (empty($noAkun) || empty($map)) {
                continue;
            }

            $hargaRaw = $row[12] ?? null;
            $harga    = $this->parseNumber($hargaRaw);

            // Log untuk debug jika harga null
            if ($harga === null && $hargaRaw !== null && $hargaRaw !== '') {
                Log::warning('[ImportJurnal] Harga tidak terbaca', [
                    'raw'   => $hargaRaw,
                    'type'  => gettype($hargaRaw),
                    'noAkun'=> $noAkun,
                ]);
            }

            $currentJurnal['items'][] = [
                'nama_akun'  => trim((string) ($row[0] ?? '')),
                'tgl'        => $this->parseDate($row[1] ?? null),
                'no_akun'    => $noAkun,
                'mm'         => trim((string) ($row[5] ?? '')),
                'nama'       => trim((string) ($row[6] ?? '')),
                'keterangan' => trim((string) ($row[7] ?? '')),
                'map'        => $map,
                'hit_kbk'    => $this->parseHitKbk($row[9]  ?? null),
                'banyak'     => $this->parseNumber($row[10] ?? null),
                'm3'         => $this->parseNumber($row[11] ?? null),
                'harga'      => $harga ?? 0,  // fallback 0 agar tidak null di DB
                'total'      => $this->parseNumber($row[13] ?? null),
            ];
        }

        // Simpan jurnal terakhir
        if ($currentJurnal && !empty($currentJurnal['items'])) {
            $jurnals[] = $currentJurnal;
        }

        return $jurnals;
    }

    // ══════════════════════════════════════════════════════════════
    // SIMPAN: Buat header + item ke database
    // ══════════════════════════════════════════════════════════════

    private function simpanJurnal(array $jurnal, int $userId): void
    {
        $noDokumen = $jurnal['no_dokumen'];

        // Cek duplikat
        $sudahAda = JurnalPembantuHeader::where('no_dokumen', $noDokumen)
            ->where('modul_asal', 'produksi')
            ->exists();

        if ($sudahAda) {
            $this->errors[] = "Jurnal '{$noDokumen}' sudah ada, dilewati.";
            return;
        }

        $tglPertama = collect($jurnal['items'])->first()['tgl'] ?? now()->format('Y-m-d');
        $noJurnal   = $this->nextNomorJurnal();

        // Kelompokkan item per akun + map
        $grouped = collect($jurnal['items'])->groupBy(fn($i) => $i['no_akun'] . '|' . $i['map']);

        $headersDbuat = [];

        foreach ($grouped as $key => $items) {
            $firstItem  = $items->first();
            $noAkun     = $firstItem['no_akun'];
            $map        = $firstItem['map'];
            $akun       = $this->resolveAkun($noAkun);
            $keterangan = $firstItem['nama'] ?: ($firstItem['keterangan'] ?: $noDokumen);

            $header = JurnalPembantuHeader::create([
                'no_jurnal_pembantu'  => $this->nextNomorPembantu(),
                'tgl_transaksi'       => $tglPertama,
                'jenis_transaksi'     => 'produksi',
                'modul_asal'          => 'produksi',
                'jurnal'              => $noJurnal,
                'no_akun'             => $akun['kode'],
                'nama_akun'           => !empty($akun['nama']) ? $akun['nama'] : $firstItem['nama_akun'],
                'map'                 => $map,
                'keterangan'          => $keterangan . ' | No.Jurnal: ' . $noDokumen,
                'no_dokumen'          => $noDokumen,
                'total_nilai'         => 0,
                'status'              => JurnalPembantuHeader::STATUS_DRAFT,
                'adalah_jurnal_balik' => false,
                'dibuat_oleh'         => $userId,
            ]);

            $urut = 1;
            foreach ($items as $item) {
                $jumlah = $this->hitungJumlah($item);

                JurnalPembantuItem::create([
    'jurnal_pembantu_header_id' => $header->id,
    'urut'                      => $urut++,
    'nama_barang'               => $item['keterangan'] ?: $item['nama_akun'],
    'no_dokumen'                => $noDokumen,
    'keterangan'                => $item['keterangan'],
    'banyak'                    => $item['banyak'],
    'm3'                        => $item['m3'],
    'harga'                     => $item['harga'] ?? 0,  // ← tambah ?? 0
    'hit_kbk'                   => $item['hit_kbk'],
    'jumlah'                    => $jumlah,
    'status'                    => true,
    'created_by'                => $userId,
    'updated_by'                => $userId,
]);
            }

            // Recalculate total_nilai header dari items yang sudah tersimpan
            $header->recalculateTotalNilai();
            $headersDbuat[] = $header->no_akun . ' (' . strtoupper($map) . ')';
        }

        $this->results[] = [
            'no_dokumen'   => $noDokumen,
            'headers'      => $headersDbuat,
            'jumlah_baris' => count($jurnal['items']),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════════

    private function hitungJumlah(array $item): float
    {
        return match ($item['hit_kbk']) {
            'm' => (float) $item['harga'] * (float) ($item['m3']    ?? 0),
            'b' => (float) $item['harga'] * (float) ($item['banyak'] ?? 0),
            default => (float) ($item['total'] ?? 0),
        };
    }

    private function parseDate(mixed $val): string
    {
        if (empty($val)) return now()->format('Y-m-d');

        // PhpSpreadsheet serial date (float/int)
        if (is_numeric($val) && $val > 1000) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $val)
                    ->format('Y-m-d');
            } catch (\Exception) {}
        }

        $str = trim((string) $val);
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'm/d/Y', 'd-M-Y'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $str);
            if ($dt) return $dt->format('Y-m-d');
        }

        return now()->format('Y-m-d');
    }

    private function parseNumber(mixed $val): ?float
{
    if ($val === null || $val === '' || strtolower((string)$val) === 'nan') return null;
    
    // Jika sudah numeric langsung (integer/float dari PhpSpreadsheet)
    if (is_int($val) || is_float($val)) return (float) $val;
    
    // Bersihkan string: hapus spasi, titik ribuan, ganti koma desimal
    $str   = trim((string) $val);
    $clean = preg_replace('/[^\d,.\-]/', '', $str);  // hapus selain angka, koma, titik, minus
    
    // Deteksi format: "2.700.000" (titik = ribuan) vs "3.121174" (titik = desimal)
    $dotCount   = substr_count($clean, '.');
    $commaCount = substr_count($clean, ',');
    
    if ($dotCount > 1) {
        // "2.700.000" → titik = ribuan, hapus titik
        $clean = str_replace('.', '', $clean);
    } elseif ($commaCount === 1 && $dotCount === 1) {
        // "1.234,56" → titik ribuan, koma desimal
        $clean = str_replace(['.', ','], ['', '.'], $clean);
    } elseif ($commaCount === 1 && $dotCount === 0) {
        // "3,121174" → koma = desimal
        $clean = str_replace(',', '.', $clean);
    }
    // else: "3.121174" → titik = desimal, sudah benar
    
    return is_numeric($clean) ? (float) $clean : null;
}

    private function parseHitKbk(mixed $val): ?string
    {
        $v = strtolower(trim((string) ($val ?? '')));
        if ($v === 'm' || $v === 'k') return 'm';
        if ($v === 'b') return 'b';
        return null;
    }

    private function resolveAkun(string $kode): array
    {
        if (isset($this->akunCache[$kode])) {
            return $this->akunCache[$kode];
        }

        $sub = SubAnakAkun::where('kode_sub_anak_akun', $kode)->where('status', 'aktif')->first();
        if ($sub) {
            return $this->akunCache[$kode] = ['kode' => $sub->kode_sub_anak_akun, 'nama' => $sub->nama_sub_anak_akun];
        }

        $anak = AnakAkun::where('kode_anak_akun', $kode)->where('status', 'aktif')->first();
        if ($anak) {
            return $this->akunCache[$kode] = ['kode' => $anak->kode_anak_akun, 'nama' => $anak->nama_anak_akun];
        }

        $induk = IndukAkun::where('kode_induk_akun', $kode)->where('status', 'aktif')->first();
        if ($induk) {
            return $this->akunCache[$kode] = ['kode' => $induk->kode_induk_akun, 'nama' => $induk->nama_induk_akun];
        }

        return $this->akunCache[$kode] = [
            'kode' => $kode,
            'nama' => '⚠ Akun tidak ditemukan: ' . $kode,
        ];
    }

    private function nextNomorJurnal(): int
    {
        return (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0) + 1;
    }

    private function nextNomorPembantu(): int
    {
        return (JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') ?? 0) + 1;
    }
}