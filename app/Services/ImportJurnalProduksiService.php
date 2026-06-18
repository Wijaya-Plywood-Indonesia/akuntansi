<?php

namespace App\Services;

use App\Models\AnakAkun;
use App\Models\IndukAkun;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\SubAnakAkun;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportJurnalProduksiService
{
    private array $akunCache = [];
    private array $errors = [];
    private array $results = [];

    public function import(string $filePath, ?int $userId): array
    {
        $this->errors = [];
        $this->results = [];
        $userId = $userId ?? 1;

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['Gagal membaca file: ' . $e->getMessage()],
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
                'errors' => ['Sheet "jurnal produksi" tidak ditemukan di file Excel.'],
                'results' => [],
            ];
        }

        // formatData=false -> angka tetap int/float, tidak diformat jadi string
        $rows = $sheet->toArray(null, true, false, false);
        $jurnals = $this->parseJurnals($rows, $filePath);

        if (empty($jurnals)) {
            return [
                'success' => false,
                'errors' => ['Tidak ada data jurnal valid yang ditemukan di sheet "jurnal produksi". Pastikan ada baris header kolom (Nama Akun, tgl, No Akun, map, dst).'],
                'results' => [],
            ];
        }

        DB::transaction(function () use ($jurnals, $userId) {
            foreach ($jurnals as $jurnal) {
                $this->simpanJurnal($jurnal, $userId);
            }
        });

        return [
            'success' => empty($this->errors) || !empty($this->results),
            'errors' => $this->errors,
            'results' => $this->results,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // PARSER
    // ══════════════════════════════════════════════════════════════

    private function isHeaderRow(array $row): bool
    {
        $col0 = strtolower(trim((string) ($row[0] ?? '')));
        $col8 = strtolower(trim((string) ($row[8] ?? '')));

        return $col0 === 'nama akun' && in_array($col8, ['map', 'd/k']);
    }

    private function parseJurnals(array $rows, string $filePath): array
    {
        $jurnals = [];
        $currentJurnal = null;
        $isDataRow = false;

        $defaultNoDokumen = 'PRODUKSI/' . strtoupper(pathinfo($filePath, PATHINFO_FILENAME));

        foreach ($rows as $row) {
            $col0 = trim((string) ($row[0] ?? ''));

            if (str_starts_with($col0, 'No. Jurnal:')) {
                if ($currentJurnal && !empty($currentJurnal['items'])) {
                    $jurnals[] = $currentJurnal;
                }
                $noDokumen = trim(str_replace('No. Jurnal:', '', $col0));
                $currentJurnal = ['no_dokumen' => $noDokumen, 'items' => []];
                $isDataRow = false;
                continue;
            }

            if ($this->isHeaderRow($row)) {
                $isDataRow = true;

                if (!$currentJurnal) {
                    $currentJurnal = ['no_dokumen' => $defaultNoDokumen, 'items' => []];
                }
                continue;
            }

            if (empty($col0) && $this->isRowEmpty($row)) {
                if ($currentJurnal && !empty($currentJurnal['items'])) {
                    $jurnals[] = $currentJurnal;
                    $currentJurnal = null;
                }
                $isDataRow = false;
                continue;
            }

            if (!$isDataRow || !$currentJurnal || empty($col0)) {
                continue;
            }

            $noAkun = $this->cleanAkunCode($row[3] ?? '');
            $map = strtolower(trim((string) ($row[8] ?? '')));

            if (empty($noAkun) || !in_array($map, ['d', 'k'])) {
                continue;
            }

            $currentJurnal['items'][] = [
                'nama_akun' => trim((string) ($row[0] ?? '')),
                'tgl' => $this->parseDate($row[1] ?? null),
                'no_akun' => $noAkun,
                'nama' => trim((string) ($row[6] ?? '')),
                'keterangan' => trim((string) ($row[7] ?? '')),
                'map' => $map,
                'hit_kbk' => $this->parseHitKbk($row[9] ?? null),
                'banyak' => $this->parseNumber($row[10] ?? null),
                'm3' => $this->parseNumber($row[11] ?? null),
                'harga' => $this->parseNumber($row[12] ?? null) ?? 0,
                // Pastikan 'total' langsung ditarik dari index 13 excel
                'total' => $this->parseNumber($row[13] ?? null) ?? 0,
            ];
        }

        if ($currentJurnal && !empty($currentJurnal['items'])) {
            $jurnals[] = $currentJurnal;
        }

        return $jurnals;
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }

    private function cleanAkunCode(mixed $val): string
    {
        if ($val === null || $val === '')
            return '';

        if (is_float($val)) {
            $str = rtrim(rtrim(sprintf('%.4f', $val), '0'), '.');
            return $str;
        }

        return trim((string) $val);
    }

    // ══════════════════════════════════════════════════════════════
    // SIMPAN
    // ══════════════════════════════════════════════════════════════

    private function simpanJurnal(array $jurnal, int $userId): void
    {
        $noDokumen = $jurnal['no_dokumen'];

        $sudahAda = JurnalPembantuHeader::where('no_dokumen', $noDokumen)
            ->where('modul_asal', 'produksi')
            ->exists();

        if ($sudahAda) {
            $this->errors[] = "Jurnal '{$noDokumen}' sudah pernah diimport, dilewati.";
            return;
        }

        $tglPertama = collect($jurnal['items'])->first()['tgl'] ?? now()->format('Y-m-d');
        $noJurnal = $this->nextNomorJurnal();

        $grouped = collect($jurnal['items'])->groupBy(fn($i) => $i['no_akun'] . '|' . $i['map']);

        $headersDibuat = [];
        $akunTidakDitemukan = [];

        foreach ($grouped as $items) {
            $firstItem = $items->first();
            $noAkun = $firstItem['no_akun'];
            $map = $firstItem['map'];
            $akun = $this->resolveAkun($noAkun);
            $keterangan = $firstItem['nama'] ?: ($firstItem['keterangan'] ?: $noDokumen);

            if (str_starts_with($akun['nama'], '⚠')) {
                $akunTidakDitemukan[] = $noAkun;
            }

            $header = JurnalPembantuHeader::create([
                'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                'tgl_transaksi' => $tglPertama,
                'jenis_transaksi' => 'produksi',
                'modul_asal' => 'produksi',
                'jurnal' => $noJurnal,
                'no_akun' => $akun['kode'],
                'nama_akun' => $akun['nama'] ?: $firstItem['nama_akun'],
                'map' => $map,
                'keterangan' => $keterangan . ' | No.Jurnal: ' . $noDokumen,
                'no_dokumen' => $noDokumen,
                'total_nilai' => 0,
                'status' => JurnalPembantuHeader::STATUS_DRAFT,
                'adalah_jurnal_balik' => false,
                'dibuat_oleh' => $userId,
            ]);

            $urut = 1;
            foreach ($items as $item) {
                // REVISI: Menggunakan nilai Total asli yang ditarik lurus dari Excel
                // tanpa melakukan perhitungan hitungJumlah() lagi.
                $jumlah = $item['total'];

                JurnalPembantuItem::create([
                    'jurnal_pembantu_header_id' => $header->id,
                    'urut' => $urut++,
                    'nama_barang' => $item['keterangan'] ?: $item['nama_akun'],
                    'no_dokumen' => $noDokumen,
                    'keterangan' => $item['keterangan'] ?: $item['nama'],
                    'banyak' => $item['banyak'],
                    'm3' => $item['m3'],
                    'harga' => $item['harga'],
                    'hit_kbk' => $item['hit_kbk'],
                    'jumlah' => $jumlah,
                    'status' => true,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            $header->recalculateTotalNilai();
            $headersDibuat[] = $akun['kode'] . ' (' . strtoupper($map) . ')';
        }

        if (!empty($akunTidakDitemukan)) {
            $this->errors[] = "Jurnal '{$noDokumen}': akun tidak ditemukan untuk kode " . implode(', ', array_unique($akunTidakDitemukan)) . " — silakan cek mapping akun.";
        }

        $this->results[] = [
            'no_dokumen' => $noDokumen,
            'headers' => $headersDibuat,
            'jumlah_baris' => count($jurnal['items']),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════════

    private function parseDate(mixed $val): string
    {
        if (empty($val))
            return now()->format('Y-m-d');

        if (is_numeric($val) && $val > 1000) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $val)
                    ->format('Y-m-d');
            } catch (\Exception) {
            }
        }

        $str = trim((string) $val);
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'm/d/Y'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $str);
            if ($dt)
                return $dt->format('Y-m-d');
        }

        return now()->format('Y-m-d');
    }

    private function parseNumber(mixed $val): ?float
    {
        if ($val === null || $val === '')
            return null;
        if (is_int($val) || is_float($val))
            return (float) $val;

        $str = trim((string) $val);
        if (strtolower($str) === 'nan' || $str === '-' || $str === '')
            return null;

        $clean = preg_replace('/[^\d,.\-]/', '', $str);

        $dotCount = substr_count($clean, '.');
        $commaCount = substr_count($clean, ',');

        if ($dotCount > 1) {
            $clean = str_replace('.', '', $clean);
        } elseif ($commaCount === 1 && $dotCount === 1) {
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        } elseif ($commaCount === 1 && $dotCount === 0) {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function parseHitKbk(mixed $val): ?string
    {
        $v = strtolower(trim((string) ($val ?? '')));

        if ($v === 'm')
            return 'm';
        else if ($v === 'b')
            return 'b';
        else if ($v === 'k')
            return 'm';
        return null;
    }

    private function resolveAkun(string $kodeAsli): array
    {
        if (isset($this->akunCache[$kodeAsli])) {
            return $this->akunCache[$kodeAsli];
        }

        $kodeTitik = $kodeAsli;
        $kodeStrip = str_replace('.', '-', $kodeAsli);
        $kodePolosCandidate = strtok($kodeAsli, '.-');

        if ($kodeAsli === $kodePolosCandidate) {
            $anak = AnakAkun::where('kode_anak_akun', $kodeAsli)->where('status', 'aktif')->first();
            if ($anak) {
                return $this->akunCache[$kodeAsli] = [
                    'kode' => $anak->kode_anak_akun,
                    'nama' => $anak->nama_anak_akun,
                ];
            }
        }

        foreach (array_unique([$kodeTitik, $kodeStrip]) as $kandidat) {
            $sub = SubAnakAkun::where('kode_sub_anak_akun', $kandidat)->where('status', 'aktif')->first();
            if ($sub) {
                return $this->akunCache[$kodeAsli] = [
                    'kode' => $sub->kode_sub_anak_akun,
                    'nama' => $sub->nama_sub_anak_akun,
                ];
            }
        }

        if ($kodePolosCandidate && $kodePolosCandidate !== $kodeAsli) {
            $anak = AnakAkun::where('kode_anak_akun', $kodePolosCandidate)->where('status', 'aktif')->first();
            if ($anak) {
                return $this->akunCache[$kodeAsli] = [
                    'kode' => $anak->kode_anak_akun,
                    'nama' => $anak->nama_anak_akun,
                ];
            }
        }

        $induk = IndukAkun::where('kode_induk_akun', $kodeAsli)->where('status', 'aktif')->first();
        if ($induk) {
            return $this->akunCache[$kodeAsli] = [
                'kode' => $induk->kode_induk_akun,
                'nama' => $induk->nama_induk_akun,
            ];
        }

        return $this->akunCache[$kodeAsli] = [
            'kode' => $kodeAsli,
            'nama' => '⚠ Akun tidak ditemukan: ' . $kodeAsli,
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