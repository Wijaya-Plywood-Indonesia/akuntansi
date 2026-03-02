<?php

namespace App\Filament\Services;

use App\Models\AkunGroup;
use App\Models\JurnalUmum;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class NeracaService
{
    /**
     * Hitung neraca untuk 1 bulan tertentu (akumulasi s/d bulan tsb)
     * atau untuk range multi bulan jika $bulanAkhir diisi
     *
     * @param int $tahun
     * @param int $bulanAwal  1-12
     * @param int|null $bulanAkhir  jika null = sama dengan $bulanAwal
     * @return array{ aktiva: Collection, pasiva: Collection, totalAktiva: float, totalPasiva: float }
     */

    public function hitung(int $tahun, int $bulanAwal, ?int $bulanAkhir = null): array
    {
        $bulanAkhir ??= $bulanAwal;

        $tglAwal = Carbon::create($tahun, $bulanAwal, 1)->startOfMonth();
        $tglAkhir = Carbon::create($tahun, $bulanAkhir, 1)->endOfMonth();

        // --- 1. Hitung saldo per sub_anak_akun ---
        $saldoPerSubAkun = $this->hitungSaldoSubAkun($tglAwal, $tglAkhir);

        // --- 2. Load semua akun_groups dengan relasi ---
        // Ganti bagian load groups dengan ini untuk performa lebih baik:
        $groups = AkunGroup::with([
            'children' => function ($q) {
                $q->ordered()->with([
                    'anakAkuns' => function ($q2) {
                        $q2->aktif()->orderBy('kode_anak_akun')
                            ->with('subAnakAkuns:id,id_anak_akun,kode_sub_anak_akun,saldo_normal');
                    }
                ]);
            }
        ])
            ->whereNull('parent_id')
            ->visible()
            ->ordered()
            ->get();

        $result = [];
        $totalAktiva = 0;
        $totalPasiva = 0;

        foreach ($groups as $rootGroup) {
            $sections = $this->buildSections($rootGroup, $saldoPerSubAkun);

            $total = collect($sections)->sum('total');

            $result[$rootGroup->nama] = [
                'sections' => $sections,
                'total' => $total,
            ];

            if (strtoupper($rootGroup->nama) === 'AKTIVA') {
                $totalAktiva = $total;
            } else {
                $totalPasiva = $total;
            }
        }

        return [
            'aktiva' => $result['AKTIVA'] ?? ['sections' => [], 'total' => 0],
            'pasiva' => $result['PASIVA'] ?? ['sections' => [], 'total' => 0],
            'totalAktiva' => $totalAktiva,
            'totalPasiva' => $totalPasiva,
        ];
    }

    // ----------------------------------------------------------------
    // PRIVATE HELPERS
    // ----------------------------------------------------------------

    /**
     * Hitung saldo tiap sub_anak_akun dari jurnal_umums
     * Return: [ 'kode_sub_anak_akun' => saldo_float ]
     */
    private function hitungSaldoSubAkun(Carbon $dari, Carbon $sampai): array
    {
        $rows = JurnalUmum::query()
            ->whereBetween('tgl', [$dari->toDateString(), $sampai->toDateString()])
            ->selectRaw('no_akun, map, SUM(banyak * harga) as total')
            ->groupBy('no_akun', 'map')
            ->get();

        $saldo = [];

        foreach ($rows as $row) {
            $kode = $row->no_akun;
            $map = strtolower($row->map);  // 'd' atau 'k'
            $nilai = (float) $row->total;

            if (!isset($saldo[$kode])) {
                $saldo[$kode] = 0;
            }

            // Debit menambah, Kredit mengurangi (akan dibalik saat perlu)
            $saldo[$kode] += ($map === 'd') ? $nilai : -$nilai;
        }

        return $saldo;
    }

    /**
     * Build sections untuk satu root group (AKTIVA atau PASIVA)
     * Return array of:
     *   [ 'group' => AkunGroup, 'items' => [...], 'total' => float ]
     */
    private function buildSections(AkunGroup $rootGroup, array $saldoPerSubAkun): array
    {
        $sections = [];

        // Iterasi child groups (misal: Aktiva Lainnya, AKTIVA TETAP, HUTANG, EKUITAS)
        foreach ($rootGroup->children as $childGroup) {
            $items = [];
            $totalSection = 0;

            foreach ($childGroup->anakAkuns as $anakAkun) {
                // Hitung saldo dari semua sub_anak_akun di bawahnya
                $nilaiAkun = 0;

                foreach ($anakAkun->subAnakAkuns as $sub) {
                    $saldo = $saldoPerSubAkun[$sub->kode_sub_anak_akun] ?? 0;

                    // Sesuaikan tanda berdasarkan saldo_normal
                    $saldoNormal = strtolower($sub->saldo_normal ?? $anakAkun->saldo_normal ?? 'debet');

                    // Jika saldo_normal = kredit, flip tanda
                    if ($saldoNormal === 'kredit') {
                        $saldo = -$saldo;
                    }

                    $nilaiAkun += $saldo;
                }

                // Hanya tampilkan jika ada nilai (atau selalu tampil, tergantung kebutuhan)
                $items[] = [
                    'kode' => $anakAkun->kode_anak_akun,
                    'nama' => $anakAkun->nama_anak_akun,
                    'nilai' => $nilaiAkun,
                ];

                $totalSection += $nilaiAkun;
            }

            $sections[] = [
                'group' => $childGroup->nama,
                'items' => $items,
                'total' => $totalSection,
            ];
        }

        return $sections;
    }
}