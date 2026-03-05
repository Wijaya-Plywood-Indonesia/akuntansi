<?php

namespace App\Filament\Services;

use App\Models\AkunGroup;
use App\Models\BukuBesar as BukuBesarModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class NeracaService
{
    /**
     * Hitung neraca untuk banyak periode.
     *
     * @param  array  $periodeList  [ ['tahun'=>2025,'bulan'=>12], ... ]
     * @return array  Keyed by "YYYY-MM" => [ label, aktiva, pasiva, totalAktiva, totalPasiva ]
     */
    public function hitungMulti(array $periodeList): array
    {
        if (empty($periodeList)) {
            return [];
        }

        // 1. Load struktur COA sekali saja (recursive)
        $groups = $this->loadGroups();

        // 2. Hitung neraca per periode
        $result = [];

        foreach ($periodeList as $periode) {
            $tahun = (int) $periode['tahun'];
            $bulan = (int) $periode['bulan'];

            // Ambil saldo langsung dari buku_besar (satu query per periode)
            $saldo = $this->getSaldo($tahun, $bulan);

            $key = $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT);
            $label = Carbon::create($tahun, $bulan)->translatedFormat('F Y');

            $result[$key] = array_merge(
                ['label' => $label, 'tahun' => $tahun, 'bulan' => $bulan],
                $this->buildNeraca($groups, $saldo)
            );
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Load semua root AkunGroup beserta seluruh hierarki di bawahnya.
     *
     * Struktur yang di-load:
     *   AkunGroup (root: AKTIVA / PASIVA)
     *     └─ children (AKTIVA LANCAR, AKTIVA TETAP, dst.)
     *          └─ children (KAS DAN SETARA KAS, dst.)   ← level 3+
     *               └─ anakAkuns
     *                    └─ subAnakAkuns
     *
     * Untuk mendukung kedalaman tak terbatas, kita load dengan
     * childrenRecursive() lalu attach anakAkuns di leaf node saat build.
     */
    private function loadGroups(): Collection
    {
        return AkunGroup::with([
            'childrenRecursive.anakAkuns' => fn($q) => $q
                ->aktif()
                ->orderBy('kode_anak_akun')
                ->with([
                    'children' => fn($q2) => $q2
                        ->aktif()
                        ->orderBy('kode_anak_akun')
                        ->with('subAnakAkuns:id,id_anak_akun,kode_sub_anak_akun,saldo_normal'),
                    'subAnakAkuns:id,id_anak_akun,kode_sub_anak_akun,saldo_normal',
                ]),
        ])
            ->whereNull('parent_id')
            ->visible()
            ->ordered()
            ->get();
    }

    /**
     * Ambil saldo akhir bulan dari tabel buku_besar.
     * Return: [ 'no_akun' => float ]
     */
    private function getSaldo(int $tahun, int $bulan): array
    {
        $rows = BukuBesarModel::query()
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->get(['no_akun', 'saldo']);

        $saldo = [];
        foreach ($rows as $row) {
            $saldo[$row->no_akun] = (float) $row->saldo;
        }

        return $saldo;
    }

    /**
     * Build struktur neraca Aktiva / Pasiva dari saldo.
     */
    private function buildNeraca(Collection $groups, array $saldo): array
    {
        $aktiva = ['sections' => [], 'total' => 0];
        $pasiva = ['sections' => [], 'total' => 0];

        foreach ($groups as $rootGroup) {
            // Rekursif bangun sections dari semua children
            [$sections, $totalRoot] = $this->buildSections($rootGroup->childrenRecursive, $saldo);

            $data = ['sections' => $sections, 'total' => $totalRoot];

            if (strtoupper($rootGroup->nama) === 'AKTIVA') {
                $aktiva = $data;
            } elseif (strtoupper($rootGroup->nama) === 'PASIVA') {
                $pasiva = $data;
            }
        }

        return [
            'aktiva' => $aktiva,
            'pasiva' => $pasiva,
            'totalAktiva' => $aktiva['total'],
            'totalPasiva' => $pasiva['total'],
        ];
    }

    /**
     * Rekursif: bangun sections dari kumpulan AkunGroup children.
     *
     * Setiap group bisa berupa:
     *  - LEAF (tidak punya children group) → tampilkan anakAkuns langsung
     *  - BRANCH (punya children group)     → rekursif, tambahkan sub-sections
     *
     * Return: [ sections[], totalFloat ]
     */
    private function buildSections(Collection $groups, array $saldo): array
    {
        $sections = [];
        $totalAll = 0;

        foreach ($groups as $group) {
            $isLeaf = $group->children->isEmpty();

            if ($isLeaf) {
                // ── Leaf: render anakAkuns langsung ──────────────────
                $items = [];
                $totalSection = 0;

                foreach ($group->anakAkuns as $anakAkun) {
                    $nilaiAkun = $this->hitungNilaiAkun($anakAkun, $saldo);

                    $items[] = [
                        'kode' => $anakAkun->kode_anak_akun,
                        'nama' => $anakAkun->nama_anak_akun,
                        'nilai' => $nilaiAkun,
                    ];

                    $totalSection += $nilaiAkun;
                }

                $sections[] = [
                    'group' => $group->nama,
                    'items' => $items,
                    'total' => $totalSection,
                    'sub_sections' => [], // leaf tidak punya sub-section
                ];

                $totalAll += $totalSection;
            } else {
                // ── Branch: rekursif ke children ─────────────────────
                [$subSections, $totalBranch] = $this->buildSections($group->children, $saldo);

                $sections[] = [
                    'group' => $group->nama,
                    'items' => [],
                    'total' => $totalBranch,
                    'sub_sections' => $subSections,
                ];

                $totalAll += $totalBranch;
            }
        }

        return [$sections, $totalAll];
    }

    /**
     * Hitung nilai satu AnakAkun (termasuk children rekursif & subAnakAkuns).
     *
     * Prioritas pencarian saldo:
     *   1. kode_sub_anak_akun  (paling spesifik)
     *   2. kode_anak_akun      (fallback)
     */
    private function hitungNilaiAkun($anakAkun, array $saldo): float
    {
        $total = 0.0;

        // Jika punya sub_anak_akun → sum dari sub
        if ($anakAkun->subAnakAkuns->isNotEmpty()) {
            foreach ($anakAkun->subAnakAkuns as $sub) {
                $saldoNormal = strtolower($sub->saldo_normal ?? $anakAkun->saldo_normal ?? 'debet');
                $nilaiRaw = $saldo[$sub->kode_sub_anak_akun]
                    ?? $saldo[$anakAkun->kode_anak_akun]
                    ?? 0.0;

                $total += ($saldoNormal === 'kredit') ? -$nilaiRaw : $nilaiRaw;
            }
        } elseif ($anakAkun->children->isNotEmpty()) {
            // Punya children (self-referential AnakAkun) → rekursif
            foreach ($anakAkun->children as $child) {
                $total += $this->hitungNilaiAkun($child, $saldo);
            }
        } else {
            // Leaf: langsung dari kode_anak_akun
            $saldoNormal = strtolower($anakAkun->saldo_normal ?? 'debet');
            $nilaiRaw = $saldo[$anakAkun->kode_anak_akun] ?? 0.0;
            $total = ($saldoNormal === 'kredit') ? -$nilaiRaw : $nilaiRaw;
        }

        return $total;
    }
}