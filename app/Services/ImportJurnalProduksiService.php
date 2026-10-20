<?php

namespace App\Services;

use App\Models\AnakAkun;
use App\Models\Barang;
use App\Models\IndukAkun;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\Kategori;
use App\Models\Satuan;
use App\Models\SubAnakAkun;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ImportJurnalProduksiService
{
    private array $akunCache = [];

    private array $barangCache = [];

    private array $errors = [];

    private array $warnings = [];

    private array $results = [];

    private array $barangBaruDibuat = [];

    public function import(string $filePath, ?int $userId): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->results = [];
        $this->barangBaruDibuat = [];
        $this->currentJurnalNo = null;
        $this->currentPembantuNo = null;
        $this->timpaDraft = false; // import Excel: jurnal yang sudah ada dilewati
        $this->dokumen = [];
        $userId = $userId ?? 1;

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['Gagal membaca file: '.$e->getMessage()],
                'results' => [],
            ];
        }

        // Cari sheet "jurnal produksi" (case-insensitive)
        $sheet = null;
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (strtolower(trim($name)) === 'jurnal produksi v2') {
                $sheet = $spreadsheet->getSheetByName($name);
                break;
            }
        }

        if (! $sheet) {
            return [
                'success' => false,
                'errors' => ['Sheet "jurnal produksi v2" tidak ditemukan di file Excel.'],
                'results' => [],
            ];
        }

        // formatData=false -> angka tetap int/float, tidak diformat jadi string
        $rows = $sheet->toArray(null, true, false, false);
        $jurnals = $this->parseJurnals($rows, $filePath);

        if (empty($jurnals)) {
            return [
                'success' => false,
                'errors' => ['Tidak ada data jurnal valid yang ditemukan di sheet "jurnal produksi v2". Pastikan ada baris header kolom (Nama Akun, tgl, No Akun, map, dst).'],
                'results' => [],
            ];
        }

        return $this->prosesJurnals($jurnals, $userId);
    }

    /**
     * Import dari API (ERP). Struktur $jurnals sama dengan hasil parseJurnals():
     *   [ ['no_dokumen' => 'DRY/20261014/PAGI', 'items' => [ [nama_akun, tgl (Y-m-d),
     *       no_akun, no, nama, keterangan, map, hit_kbk, banyak, m3, harga, total,
     *       barang_identifier], ... ]], ... ]
     *
     * Beda dengan import Excel: jurnal yang sudah ada TIDAK dilewati, tetapi
     * ditimpa selama semua barisnya masih berstatus draft. Kalau sudah ada yang
     * diposting, jurnal itu ditolak (tidak diubah).
     *
     * $modulAsal: 'produksi' (bawaan) atau 'pembelian_kayu' (kayu masuk).
     */
    public function importDariApi(array $jurnals, ?int $userId, string $modulAsal = 'produksi'): array
    {
        $this->resetState();
        $this->timpaDraft = true;
        $this->modulAsal = $modulAsal;

        return $this->prosesJurnals($jurnals, $userId ?? 1);
    }

    private bool $timpaDraft = false;

    /** modul_asal yang dipakai saat menyimpan header ('produksi' atau 'pembelian_kayu'). */
    private string $modulAsal = 'produksi';

    /** Status per no_dokumen: dibuat | diperbarui | ditolak | dilewati */
    private array $dokumen = [];

    private function resetState(): void
    {
        $this->errors = [];
        $this->warnings = [];
        $this->results = [];
        $this->dokumen = [];
        $this->modulAsal = 'produksi';
        $this->barangBaruDibuat = [];
        $this->currentJurnalNo = null;
        $this->currentPembantuNo = null;
    }

    private function prosesJurnals(array $jurnals, int $userId): array
    {
        DB::transaction(function () use ($jurnals, $userId) {
            foreach ($jurnals as $jurnal) {
                $this->simpanJurnal($jurnal, $userId);
            }
        });

        // Gabungkan warning barang baru ke errors/warnings supaya kelihatan di notifikasi
        if (! empty($this->barangBaruDibuat)) {
            $this->warnings[] = 'Barang baru otomatis dibuat ('.count($this->barangBaruDibuat).'): '
                .implode(', ', array_slice(array_unique($this->barangBaruDibuat), 0, 20))
                .(count(array_unique($this->barangBaruDibuat)) > 20 ? ', ...' : '');
        }

        return [
            'success' => empty($this->errors) || ! empty($this->results),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'results' => $this->results,
            'dokumen' => $this->dokumen,
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

        $defaultNoDokumenBase = 'PRODUKSI/'.strtoupper(pathinfo($filePath, PATHINFO_FILENAME));
        $defaultDocCount = 1;

        foreach ($rows as $row) {
            $col0 = trim((string) ($row[0] ?? ''));

            if (str_starts_with($col0, 'No. Jurnal:')) {
                if ($currentJurnal && ! empty($currentJurnal['items'])) {
                    $jurnals[] = $currentJurnal;
                }
                $noDokumen = trim(str_replace('No. Jurnal:', '', $col0));
                $currentJurnal = ['no_dokumen' => $noDokumen, 'items' => []];
                $isDataRow = false;

                continue;
            }

            if ($this->isHeaderRow($row)) {
                $isDataRow = true;

                if (! $currentJurnal) {
                    $currentJurnal = ['no_dokumen' => $defaultNoDokumenBase.'-'.$defaultDocCount++, 'items' => []];
                }

                continue;
            }

            if (empty($col0) && $this->isRowEmpty($row)) {
                if ($currentJurnal && ! empty($currentJurnal['items'])) {
                    $jurnals[] = $currentJurnal;
                    $currentJurnal = null;
                }
                $isDataRow = false;

                continue;
            }

            if (! $isDataRow || ! $currentJurnal || empty($col0)) {
                continue;
            }

            $noAkun = $this->cleanAkunCode($row[3] ?? '');
            $map = strtolower(trim((string) ($row[8] ?? '')));

            if (empty($noAkun) || ! in_array($map, ['d', 'k'])) {
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
                // Kolom "ID Barang" mentah dari Excel (index 14).
                // Kosong => id_barang kosong. Angka => dianggap barangs.id.
                'barang_identifier' => trim((string) ($row[14] ?? '')),
            ];
        }

        if ($currentJurnal && ! empty($currentJurnal['items'])) {
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
        if ($val === null || $val === '') {
            return '';
        }

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

        // 'produksi' ikut dicari supaya jurnal yang dulu tersimpan di modul itu
        // (mis. Kayu Masuk sebelum dipisah) dipindahkan, bukan terduplikasi.
        $lama = JurnalPembantuHeader::where('no_dokumen', $noDokumen)
            ->whereIn('modul_asal', array_unique(['produksi', $this->modulAsal]))
            ->get();

        $noJurnalLama = null;

        if ($lama->isNotEmpty()) {
            if (! $this->timpaDraft) {
                // Perilaku import Excel (tidak berubah): dilewati.
                $this->errors[] = "Jurnal '{$noDokumen}' sudah pernah diimport, dilewati.";
                $this->dokumen[$noDokumen] = ['status' => 'dilewati', 'message' => 'Sudah pernah diimport.'];

                return;
            }

            $belumDraft = $lama->first(fn ($h) => $h->status !== JurnalPembantuHeader::STATUS_DRAFT);
            if ($belumDraft) {
                $pesan = "Jurnal '{$noDokumen}' sudah berstatus '{$belumDraft->status}' di akuntansi, tidak bisa ditimpa. "
                    .'Batalkan/balik jurnal itu di akuntansi bila memang perlu dikirim ulang.';
                $this->errors[] = $pesan;
                $this->dokumen[$noDokumen] = ['status' => 'ditolak', 'message' => $pesan];

                return;
            }

            // Masih draft semua: hapus versi lama (item ikut terhapus lewat cascade),
            // nomor jurnal dipertahankan supaya tidak muncul nomor baru tiap kirim ulang.
            $noJurnalLama = (int) $lama->first()->jurnal;
            JurnalPembantuHeader::whereIn('id', $lama->pluck('id'))->delete();
        }

        $tglPertama = collect($jurnal['items'])->first()['tgl'] ?? now()->format('Y-m-d');
        $noJurnal = $noJurnalLama ?: $this->nextNomorJurnal();

        $headersDibuat = [];
        $akunTidakDitemukan = [];

        foreach ($jurnal['items'] as $item) {
            $noAkun = $item['no_akun'];
            $map = $item['map'];
            $akun = $this->resolveAkun($noAkun);
            $keterangan = $item['nama'] ?: ($item['keterangan'] ?: $noDokumen);

            $idBarang = null;
            $identifier = trim((string) ($item['barang_identifier'] ?? ''));

            if (str_starts_with($akun['nama'], '⚠')) {
                $akunTidakDitemukan[] = $noAkun;
            } else {
                // ── LOGIC LAMA (DINONAKTIFKAN) ───────────────────────────────
                // Dulu: akun ketemu -> selalu resolve/auto-create barang, walaupun
                // kolom ID Barang di Excel kosong (jadi Hutang Gaji, Selisih, dst.
                // ikut punya id_barang), dan angka di Excel dianggap kode_barang
                // sehingga barang baru dibuat (mis. 270 -> id 1826).
                if (false) {
                    $subAkun = SubAnakAkun::where('kode_sub_anak_akun', $akun['kode'])->first();

                    $barang = $this->resolveBarang(
                        kodeAkun: $akun['kode'],
                        idSubAnakAkun: $subAkun?->id,
                        identifierAsli: $item['barang_identifier'],
                        namaAkunAsli: $item['nama_akun'],
                        keteranganAsli: $item['keterangan'] ?: $item['nama']
                    );

                    $idBarang = $barang->id;

                    if ($barang->wasRecentlyCreated) {
                        $this->barangBaruDibuat[] = $barang->nama_barang;
                    }
                }

                // ── LOGIC BARU ───────────────────────────────────────────────
                // Kosong di Excel => id_barang kosong (tidak ada auto-create).
                // Angka di Excel  => dianggap barangs.id, dicari langsung.
                // Bukan angka     => perilaku lama (dianggap kode_barang).
                if ($identifier !== '') {
                    if (ctype_digit($identifier)) {
                        $barang = Barang::find((int) $identifier);

                        if ($barang) {
                            $idBarang = $barang->id;
                        } else {
                            $this->errors[] = "Jurnal '{$noDokumen}': ID Barang {$identifier} tidak ditemukan "
                                ."(akun {$akun['kode']}) — id_barang dikosongkan.";
                        }
                    } else {
                        $subAkun = SubAnakAkun::where('kode_sub_anak_akun', $akun['kode'])->first();

                        $barang = $this->resolveBarang(
                            kodeAkun: $akun['kode'],
                            idSubAnakAkun: $subAkun?->id,
                            identifierAsli: $identifier,
                            namaAkunAsli: $item['nama_akun'],
                            keteranganAsli: $item['keterangan'] ?: $item['nama']
                        );

                        $idBarang = $barang->id;

                        if ($barang->wasRecentlyCreated) {
                            $this->barangBaruDibuat[] = $barang->nama_barang;
                        }
                    }
                }
            }

            $header = JurnalPembantuHeader::create([
                'no_jurnal_pembantu' => $this->nextNomorPembantu(),
                'tgl_transaksi' => $tglPertama,
                'jenis_transaksi' => $this->modulAsal === 'pembelian_kayu' ? 'bm' : 'produksi',
                'modul_asal' => $this->modulAsal,
                'jurnal' => $noJurnal,
                'no_akun' => $akun['kode'],
                'nama_akun' => $akun['nama'] ?: $item['nama_akun'],
                'map' => $map,
                'keterangan' => $keterangan
                    .(filled($item['no'] ?? null) ? ' | Seri: '.$item['no'] : '')
                    .' | No.Jurnal: '.$noDokumen,
                'no_dokumen' => $noDokumen,
                'total_nilai' => 0,
                'status' => JurnalPembantuHeader::STATUS_DRAFT,
                'adalah_jurnal_balik' => false,
                'dibuat_oleh' => $userId,
            ]);

            $jumlah = $item['total'];

            JurnalPembantuItem::create([
                'jurnal_pembantu_header_id' => $header->id,
                'urut' => 1,
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
                'id_barang' => $idBarang,
            ]);

            $header->recalculateTotalNilai();
            $headersDibuat[] = $akun['kode'].' ('.strtoupper($map).')';
        }

        if (! empty($akunTidakDitemukan)) {
            $this->errors[] = "Jurnal '{$noDokumen}': akun tidak ditemukan untuk kode ".implode(', ', array_unique($akunTidakDitemukan)).' — silakan cek mapping akun.';
        }

        $this->results[] = [
            'no_dokumen' => $noDokumen,
            'headers' => $headersDibuat,
            'jumlah_baris' => count($jurnal['items']),
        ];

        $this->dokumen[$noDokumen] = [
            'status' => $noJurnalLama ? 'diperbarui' : 'dibuat',
            'no_jurnal' => $noJurnal,
            'jumlah_baris' => count($jurnal['items']),
            'message' => $noJurnalLama ? 'Jurnal draft lama ditimpa dengan data terbaru.' : 'Jurnal dibuat.',
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // RESOLUSI / AUTO-CREATE BARANG
    // ══════════════════════════════════════════════════════════════

    /**
     * Buang suffix/prefix "kelebihan" / "kehilangan" dari repair-journal
     * supaya baris itu tetap match ke barang yang SAMA dengan baris aslinya
     * (bukan dianggap barang baru).
     */
    private function normalisasiKeteranganBarang(string $keterangan): string
    {
        $k = trim($keterangan);

        // buang prefix "Kehilangan " di depan
        $k = preg_replace('/^kehilangan\s+/i', '', $k);

        // buang suffix "// kelebihan" / "// kehilangan" (dengan variasi spasi)
        $k = preg_replace('/\s*\/\/\s*(kelebihan|kehilangan)\s*$/i', '', $k);

        return trim($k);
    }

    /**
     * Cari atau buat Barang untuk satu baris jurnal produksi.
     * Key pencocokan (urutan prioritas):
     *   1. barang_identifier dari kolom Excel (row 14) kalau terisi -> paling akurat
     *   2. kombinasi kode akun + keterangan (dinormalisasi) -> fallback
     *
     * CATATAN: setelah perubahan di simpanJurnal(), method ini hanya dipanggil
     * kalau identifier terisi DAN bukan angka murni (dianggap kode_barang).
     */
    private function resolveBarang(
        string $kodeAkun,
        ?int $idSubAnakAkun,
        string $identifierAsli,
        string $namaAkunAsli,
        string $keteranganAsli
    ): Barang {
        $keteranganBersih = $this->normalisasiKeteranganBarang($keteranganAsli);

        if (trim($identifierAsli) !== '') {
            $slugKey = strtolower(trim($identifierAsli));
        } else {
            $slugKey = strtolower($kodeAkun.'|'.$keteranganBersih);
        }
        $slugKey = preg_replace('/\s+/', ' ', $slugKey);

        if (isset($this->barangCache[$slugKey])) {
            return $this->barangCache[$slugKey];
        }

        $kodeBarang = trim($identifierAsli) !== ''
            ? trim($identifierAsli)
            : 'VNR-'.substr(md5($slugKey), 0, 12);

        $namaBarang = trim($namaAkunAsli.' - '.$keteranganBersih, ' -');
        if ($namaBarang === '') {
            $namaBarang = $kodeBarang;
        }

        $barang = Barang::firstOrCreate(
            ['kode_barang' => $kodeBarang],
            [
                'nama_barang' => $namaBarang,
                'id_sub_anak_akun' => $idSubAnakAkun,
                'id_kategori' => $this->getOrCreateKategoriVeneer(),
                'id_satuan' => $this->getOrCreateSatuanLembar(),
                'harga_beli' => 0,
                'harga_jual' => 0,
                'stok_minimum' => 0,
                'is_active' => true,
            ]
        );

        return $this->barangCache[$slugKey] = $barang;
    }

    private ?int $kategoriVeneerId = null;

    private function getOrCreateKategoriVeneer(): int
    {
        if ($this->kategoriVeneerId) {
            return $this->kategoriVeneerId;
        }

        // TODO: sesuaikan dengan id_kategori master yang sudah ada kalau berbeda
        $kat = Kategori::firstOrCreate(['nama_kategori' => 'Veneer']);

        return $this->kategoriVeneerId = $kat->id;
    }

    private ?int $satuanLembarId = null;

    private function getOrCreateSatuanLembar(): int
    {
        if ($this->satuanLembarId) {
            return $this->satuanLembarId;
        }

        // TODO: sesuaikan dengan id_satuan master yang sudah ada kalau berbeda
        $sat = Satuan::firstOrCreate(['nama_satuan' => 'Lembar']);

        return $this->satuanLembarId = $sat->id;
    }

    // ══════════════════════════════════════════════════════════════
    // HELPER
    // ══════════════════════════════════════════════════════════════

    private function parseDate(mixed $val): string
    {
        if (empty($val)) {
            return now()->format('Y-m-d');
        }

        if (is_numeric($val) && $val > 1000) {
            try {
                return Date::excelToDateTimeObject((float) $val)
                    ->format('Y-m-d');
            } catch (\Exception) {
            }
        }

        $str = trim((string) $val);
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'm/d/Y'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $str);
            if ($dt) {
                return $dt->format('Y-m-d');
            }
        }

        return now()->format('Y-m-d');
    }

    private function parseNumber(mixed $val): ?float
    {
        if ($val === null || $val === '') {
            return null;
        }
        if (is_int($val) || is_float($val)) {
            return (float) $val;
        }

        $str = trim((string) $val);
        if (strtolower($str) === 'nan' || $str === '-' || $str === '') {
            return null;
        }

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

        if ($v === 'm') {
            return 'm';
        } elseif ($v === 'b') {
            return 'b';
        } elseif ($v === 'k') {
            return 'm';
        }

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
            'nama' => '⚠ Akun tidak ditemukan: '.$kodeAsli,
        ];
    }

    private ?int $currentJurnalNo = null;

    private ?int $currentPembantuNo = null;

    private function nextNomorJurnal(): int
    {
        if ($this->currentJurnalNo === null) {
            $this->currentJurnalNo = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0);
        }

        return ++$this->currentJurnalNo;
    }

    private function nextNomorPembantu(): int
    {
        if ($this->currentPembantuNo === null) {
            $this->currentPembantuNo = (int) (JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') ?? 0);
        }

        return ++$this->currentPembantuNo;
    }
}