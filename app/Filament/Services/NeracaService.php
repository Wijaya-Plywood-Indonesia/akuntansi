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

        $groups = $this->loadGroups();
        $result = [];

        foreach ($periodeList as $periode) {
            $tahun = (int) $periode['tahun'];
            $bulan = (int) $periode['bulan'];

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
     * Catatan struktur data aktual:
     * - Pivot akun_group_anak_akun sudah berisi children langsung,
     *   bukan parent (id=1 'Kas dan Setara Kas', id=11 'Aktiva Tetap' tidak ada di pivot)
     * - Children AnakAkun di-load rekursif untuk kalkulasi di hitungNilaiAkun()
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
                        ->with([
                            'children' => fn($q3) => $q3
                                ->aktif()
                                ->orderBy('kode_anak_akun')
                                ->with('subAnakAkuns:id,id_anak_akun,kode_sub_anak_akun,saldo_normal'),
                            'subAnakAkuns:id,id_anak_akun,kode_sub_anak_akun,saldo_normal',
                        ]),
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
     *
     * !! PENTING: Saldo diambil APA ADANYA — termasuk nilai NEGATIF !!
     *
     * Akumulasi penyusutan sudah tersimpan negatif di buku_besar:
     *   125-01 (Akm. Penyusutan Kendaraan) = -7.910.465,62
     *   127-01 (Akm. Penyusutan Inventaris) = -775.000
     *   131-01 (Akm. Penyusutan Mesin)     = -88.541.662,33
     *
     * Jika di-abs() maka penyusutan akan MENAMBAH aktiva → salah!
     *
     * Return: [ 'no_akun' => float ]  (positif atau negatif)
     */
    private function getSaldo(int $tahun, int $bulan): array
    {
        $rows = BukuBesarModel::query()
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->get(['no_akun', 'saldo']);

        $saldo = [];
        foreach ($rows as $row) {
            $saldo[$row->no_akun] = (float) $row->saldo; // as-is, jangan abs()
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
     */
    private function buildSections(Collection $groups, array $saldo): array
    {
        $sections = [];
        $totalAll = 0;

        foreach ($groups as $group) {
            $isLeaf = $group->children->isEmpty();

            if ($isLeaf) {
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
                    'sub_sections' => [],
                ];

                $totalAll += $totalSection;

            } else {
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
     * Hitung nilai satu AnakAkun dari buku_besar.
     *
     * ╔══════════════════════════════════════════════════════════════╗
     * ║  ATURAN: Ambil saldo as-is dari buku_besar (sign sudah benar)║
     * ║                                                              ║
     * ║  Aktiva normal  → positif di buku_besar → tampil positif    ║
     * ║  Akm.Penyusutan → NEGATIF di buku_besar → mengurangi aktiva ║
     * ║  Hutang/Modal   → positif di buku_besar → tampil positif    ║
     * ║                                                              ║
     * ║  Verifikasi Jan 2025: Total Aktiva = 2.474.435.711 ✓        ║
     * ╚══════════════════════════════════════════════════════════════╝
     *
     * Hierarki kalkulasi:
     *   1. Punya subAnakAkuns → sum saldo tiap sub (misal 114-01, 115-01, dst)
     *   2. Punya children     → rekursif ke children saja (parent tidak punya saldo sendiri)
     *   3. Leaf               → ambil saldo dari kode_anak_akun
     */
    private function hitungNilaiAkun($anakAkun, array $saldo): float
    {
        $total = 0.0;

        if ($anakAkun->subAnakAkuns->isNotEmpty()) {
            // Punya sub_anak_akun (misal Piutang punya 114-01, 114-02, dst)
            foreach ($anakAkun->subAnakAkuns as $sub) {
                // Ambil dengan kode sub — TIDAK fallback ke kode_anak_akun
                // karena kode_anak_akun (misal '114') bisa tidak ada di buku_besar
                $total += $saldo[$sub->kode_sub_anak_akun] ?? 0.0;
            }

        } elseif ($anakAkun->children->isNotEmpty()) {
            // Punya children AnakAkun (self-referential)
            // Akun parent hanya header — saldo ada di children, bukan di parent
            foreach ($anakAkun->children as $child) {
                $total += $this->hitungNilaiAkun($child, $saldo);
            }

        } else {
            // Leaf: ambil saldo langsung dari kode_anak_akun
            $total = $saldo[$anakAkun->kode_anak_akun] ?? 0.0;
        }

        return $total;
    }
}