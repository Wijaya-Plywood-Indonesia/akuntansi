<?php

namespace App\Services;

use App\Models\AkunGroup;
use App\Models\JurnalUmum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class NeracaService
{
    public function hitungMulti(array $periodeList, string $jenisFilter = 'bulan'): array
    {
        if (empty($periodeList)) {
            return [];
        }

        $groups = $this->loadGroups();
        $result = [];

        foreach ($periodeList as $periode) {
            $start = $periode['start']; // Instance Carbon
            $end   = $periode['end'];   // Instance Carbon
            $key   = $periode['date_string'];

            $saldo            = $this->getSaldoDinamis($start, $end, $jenisFilter);
            $qty              = $this->getSaldoQtyDinamis($start, $end, $jenisFilter);

            // ── PERUBAHAN 1: AMBIL SALDO M3 DINAMIS DARI DATABASE ──────────────────
            // Baris ini ditambahkan untuk memanggil method penarik saldo m3 dinamis
            $m3               = $this->getSaldoM3Dinamis($start, $end, $jenisFilter);

            $labaRugiBerjalan = $this->hitungLabaRugiBerjalanDinamis($start, $end);

            $result[$key] = array_merge(
                [
                    'label' => $periode['label'],
                    'tahun' => $periode['tahun'],
                    'bulan' => $periode['bulan']
                ],
                // ── PERUBAHAN 2: SERTAKAN VARIABEL $m3 KE DALAM BUILDER NERACA ───────
                // Parameter $m3 disisipkan di antara $qty dan $labaRugiBerjalan
                $this->buildNeraca($groups, $saldo, $qty, $m3, $labaRugiBerjalan)
            );
        }

        return $result;
    }

    private function getSaldoDinamis(Carbon $start, Carbon $end, string $jenisFilter): array
    {
        $saldoAwal = [];
        $saldoNormalMap = DB::table('sub_anak_akuns')->pluck('saldo_normal', 'kode_sub_anak_akun')->toArray();

        if ($jenisFilter === 'hari') {
            // Akumulasi jurnal dari awal mula s.d H-1
            $mutasiLalu = JurnalUmum::where('tgl', '<', $start->format('Y-m-d'))
                ->selectRaw("
                    no_akun,
                    SUM(
                        CASE WHEN LOWER(map) = 'd' THEN 
                            CASE 
                                WHEN LOWER(hit_kbk) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
                                WHEN LOWER(hit_kbk) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
                                ELSE COALESCE(harga, 0)
                            END
                        ELSE 0 END
                    ) as total_debit,
                    SUM(
                        CASE WHEN LOWER(map) = 'k' THEN 
                            CASE 
                                WHEN LOWER(hit_kbk) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
                                WHEN LOWER(hit_kbk) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
                                ELSE COALESCE(harga, 0)
                            END
                        ELSE 0 END
                    ) as total_kredit
                ")
                ->groupBy('no_akun')
                ->get()
                ->keyBy('no_akun');

            foreach ($mutasiLalu as $kode => $m) {
                $isKredit = in_array(strtolower($saldoNormalMap[$kode] ?? 'debit'), ['kredit', 'credit', 'k']);
                $saldoAwal[$kode] = $isKredit
                    ? ($m->total_kredit - $m->total_debit)
                    : ($m->total_debit - $m->total_kredit);
            }
        } else {
            // Jika bulanan, pakai tabel buku_besar bulan lalu agar efisien
            $prevDate  = $start->copy()->subMonth();
            $saldoAwal = DB::table('buku_besar')
                ->where('tahun', $prevDate->year)
                ->where('bulan', $prevDate->month)
                ->pluck('saldo', 'no_akun')
                ->toArray();
        }

        // Mutasi pada rentang tanggal/bulan terpilih
        $mutasi = JurnalUmum::whereBetween('tgl', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->selectRaw("
                no_akun,
                SUM(
                    CASE WHEN LOWER(map) = 'd' THEN 
                        CASE 
                            WHEN LOWER(hit_kbk) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
                            WHEN LOWER(hit_kbk) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
                            ELSE COALESCE(harga, 0)
                        END
                    ELSE 0 END
                ) as total_debit,
                SUM(
                    CASE WHEN LOWER(map) = 'k' THEN 
                        CASE 
                            WHEN LOWER(hit_kbk) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
                            WHEN LOWER(hit_kbk) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
                            ELSE COALESCE(harga, 0)
                        END
                    ELSE 0 END
                ) as total_kredit
            ")
            ->groupBy('no_akun')
            ->get()
            ->keyBy('no_akun');

        $semuaKode = collect(array_keys($saldoAwal))->merge($mutasi->keys())->unique();

        $result = [];
        foreach ($semuaKode as $kode) {
            $awal   = (float) ($saldoAwal[$kode] ?? 0);
            $debit  = (float) ($mutasi[$kode]->total_debit  ?? 0);
            $kredit = (float) ($mutasi[$kode]->total_kredit ?? 0);

            $isKredit = in_array(strtolower($saldoNormalMap[$kode] ?? 'debit'), ['kredit', 'credit', 'k']);
            $result[$kode] = $isKredit ? $awal + $kredit - $debit : $awal + $debit - $kredit;
        }

        return $result;
    }

    private function getSaldoQtyDinamis(Carbon $start, Carbon $end, string $jenisFilter): array
    {
        $qtyAwal = [];
        $saldoNormalMap = DB::table('sub_anak_akuns')->pluck('saldo_normal', 'kode_sub_anak_akun')->toArray();

        if ($jenisFilter === 'hari') {
            $mutasiQtyLalu = JurnalUmum::where('tgl', '<', $start->format('Y-m-d'))
                ->whereNotNull('banyak')->where('banyak', '>', 0)
                ->selectRaw("
                    no_akun,
                    SUM(CASE WHEN LOWER(map) = 'd' THEN COALESCE(banyak, 0) ELSE 0 END) as qty_debit,
                    SUM(CASE WHEN LOWER(map) = 'k' THEN COALESCE(banyak, 0) ELSE 0 END) as qty_kredit
                ")
                ->groupBy('no_akun')
                ->get()
                ->keyBy('no_akun');

            foreach ($mutasiQtyLalu as $kode => $m) {
                $isKredit = in_array(strtolower($saldoNormalMap[$kode] ?? 'debit'), ['kredit', 'credit', 'k']);
                $qtyAwal[$kode] = $isKredit
                    ? ($m->qty_kredit - $m->qty_debit)
                    : ($m->qty_debit - $m->qty_kredit);
            }
        } else {
            $prevDate = $start->copy()->subMonth();
            try {
                $qtyAwal = DB::table('buku_besar')
                    ->where('tahun', $prevDate->year)
                    ->where('bulan', $prevDate->month)
                    ->whereNotNull('qty')->where('qty', '>', 0)
                    ->pluck('qty', 'no_akun')->toArray();
            } catch (\Exception $e) {
                $qtyAwal = [];
            }
        }

        $mutasiQty = JurnalUmum::whereBetween('tgl', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereNotNull('banyak')->where('banyak', '>', 0)
            ->selectRaw("
                no_akun,
                SUM(CASE WHEN LOWER(map) = 'd' THEN COALESCE(banyak, 0) ELSE 0 END) as qty_debit,
                SUM(CASE WHEN LOWER(map) = 'k' THEN COALESCE(banyak, 0) ELSE 0 END) as qty_kredit
            ")
            ->groupBy('no_akun')
            ->get()
            ->keyBy('no_akun');

        $semuaKode = collect(array_keys($qtyAwal))->merge($mutasiQty->keys())->unique();

        $result = [];
        foreach ($semuaKode as $kode) {
            $awal = (float) ($qtyAwal[$kode] ?? 0);
            $qtyD = (float) ($mutasiQty[$kode]->qty_debit  ?? 0);
            $qtyK = (float) ($mutasiQty[$kode]->qty_kredit ?? 0);

            if ($qtyD == 0 && $qtyK == 0 && $awal == 0) continue;

            $isKredit = in_array(strtolower($saldoNormalMap[$kode] ?? 'debit'), ['kredit', 'credit', 'k']);
            $net = $isKredit ? $awal + $qtyK - $qtyD : $awal + $qtyD - $qtyK;

            if ($net != 0) $result[$kode] = $net;
        }

        return $result;
    }

    // ── PERUBAHAN 3: MENAMBAHKAN METHOD BARU UNTUK SALDO M3 DINAMIS ────────────────────
    // Metode ini sepenuhnya baru untuk mengakumulasi nilai volume m3 dari Jurnal Umum / Buku Besar
    private function getSaldoM3Dinamis(Carbon $start, Carbon $end, string $jenisFilter): array
    {
        $m3Awal = [];
        $saldoNormalMap = DB::table('sub_anak_akuns')->pluck('saldo_normal', 'kode_sub_anak_akun')->toArray();

        if ($jenisFilter === 'hari') {
            $mutasiM3Lalu = JurnalUmum::where('tgl', '<', $start->format('Y-m-d'))
                ->whereNotNull('m3')->where('m3', '>', 0)
                ->selectRaw("
                    no_akun,
                    SUM(CASE WHEN LOWER(map) = 'd' THEN COALESCE(m3, 0) ELSE 0 END) as m3_debit,
                    SUM(CASE WHEN LOWER(map) = 'k' THEN COALESCE(m3, 0) ELSE 0 END) as m3_kredit
                ")
                ->groupBy('no_akun')
                ->get()
                ->keyBy('no_akun');

            foreach ($mutasiM3Lalu as $kode => $m) {
                $isKredit = in_array(strtolower($saldoNormalMap[$kode] ?? 'debit'), ['kredit', 'credit', 'k']);
                $m3Awal[$kode] = $isKredit
                    ? ($m->m3_kredit - $m->m3_debit)
                    : ($m->m3_debit - $m->m3_kredit);
            }
        } else {
            $prevDate = $start->copy()->subMonth();
            try {
                $m3Awal = DB::table('buku_besar')
                    ->where('tahun', $prevDate->year)
                    ->where('bulan', $prevDate->month)
                    ->whereNotNull('m3')->where('m3', '>', 0)
                    ->pluck('m3', 'no_akun')->toArray();
            } catch (\Exception $e) {
                $m3Awal = [];
            }
        }

        $mutasiM3 = JurnalUmum::whereBetween('tgl', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereNotNull('m3')->where('m3', '>', 0)
            ->selectRaw("
                no_akun,
                SUM(CASE WHEN LOWER(map) = 'd' THEN COALESCE(m3, 0) ELSE 0 END) as m3_debit,
                SUM(CASE WHEN LOWER(map) = 'k' THEN COALESCE(m3, 0) ELSE 0 END) as m3_kredit
            ")
            ->groupBy('no_akun')
            ->get()
            ->keyBy('no_akun');

        $semuaKode = collect(array_keys($m3Awal))->merge($mutasiM3->keys())->unique();

        $result = [];
        foreach ($semuaKode as $kode) {
            $awal = (float) ($m3Awal[$kode] ?? 0);
            $m3D  = (float) ($mutasiM3[$kode]->m3_debit  ?? 0);
            $m3K  = (float) ($mutasiM3[$kode]->m3_kredit ?? 0);

            if ($m3D == 0 && $m3K == 0 && $awal == 0) continue;

            $isKredit = in_array(strtolower($saldoNormalMap[$kode] ?? 'debit'), ['kredit', 'credit', 'k']);
            $net = $isKredit ? $awal + $m3K - $m3D : $awal + $m3D - $m3K;

            if ($net != 0) $result[$kode] = $net;
        }

        return $result;
    }

    private function hitungLabaRugiBerjalanDinamis(Carbon $start, Carbon $end): float
    {
        $rootLabaRugi = DB::table('akun_groups')->whereNull('parent_id')
            ->whereRaw('LOWER(nama) LIKE ?', ['%laba rugi%'])->first();

        if (!$rootLabaRugi) return 0.0;

        $allGroupIds = $this->getAllChildGroupIds($rootLabaRugi->id);
        if (empty($allGroupIds)) return 0.0;

        $akunLabaRugi = DB::table('akun_group_anak_akun as pivot')
            ->join('anak_akuns as aa', 'aa.id', '=', 'pivot.anak_akun_id')
            ->join('sub_anak_akuns as saa', 'saa.id_anak_akun', '=', 'aa.id')
            ->whereIn('pivot.akun_group_id', $allGroupIds)
            ->select('saa.kode_sub_anak_akun', 'saa.saldo_normal')
            ->get()->keyBy('kode_sub_anak_akun');

        if ($akunLabaRugi->isEmpty()) return 0.0;

        $mutasi = JurnalUmum::whereBetween('tgl', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereIn('no_akun', $akunLabaRugi->keys()->toArray())
            ->selectRaw("
                no_akun,
                SUM(
                    CASE WHEN LOWER(map) = 'd' THEN 
                        CASE 
                            WHEN LOWER(hit_kbk) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
                            WHEN LOWER(hit_kbk) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
                            ELSE COALESCE(harga, 0)
                        END
                    ELSE 0 END
                ) as total_debit,
                SUM(
                    CASE WHEN LOWER(map) = 'k' THEN 
                        CASE 
                            WHEN LOWER(hit_kbk) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
                            WHEN LOWER(hit_kbk) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
                            ELSE COALESCE(harga, 0)
                        END
                    ELSE 0 END
                ) as total_kredit
            ")
            ->groupBy('no_akun')->get()->keyBy('no_akun');

        $laba = 0.0;
        foreach ($akunLabaRugi as $kode => $akun) {
            $debit  = (float) ($mutasi[$kode]->total_debit  ?? 0);
            $kredit = (float) ($mutasi[$kode]->total_kredit ?? 0);
            $isKredit = in_array(strtolower($akun->saldo_normal ?? 'debit'), ['kredit', 'credit', 'k']);

            if ($isKredit) {
                $laba += $kredit - $debit;
            } else {
                $laba -= $debit - $kredit;
            }
        }

        return $laba;
    }

    private function getAllChildGroupIds(int $parentId): array
    {
        $ids      = [];
        $children = DB::table('akun_groups')
            ->where('parent_id', $parentId)
            ->pluck('id')
            ->toArray();

        foreach ($children as $childId) {
            $ids[] = $childId;
            $ids   = array_merge($ids, $this->getAllChildGroupIds($childId));
        }
        return $ids;
    }

    private function loadGroups(): Collection
    {
        return AkunGroup::with([
            'anakAkuns' => fn($q) => $q->orderBy('kode_anak_akun')
                ->with([
                    'subAnakAkuns' => fn($q2) => $q2->orderBy('kode_sub_anak_akun')
                        ->select(['sub_anak_akuns.id', 'id_anak_akun', 'kode_sub_anak_akun', 'nama_sub_anak_akun', 'saldo_normal']),
                ]),
            'childrenRecursive.anakAkuns' => fn($q) => $q->orderBy('kode_anak_akun')
                ->with([
                    'subAnakAkuns' => fn($q2) => $q2->orderBy('kode_sub_anak_akun')
                        ->select(['sub_anak_akuns.id', 'id_anak_akun', 'kode_sub_anak_akun', 'nama_sub_anak_akun', 'saldo_normal']),
                    'children' => fn($q3) => $q3->orderBy('kode_anak_akun')
                        ->with([
                            'subAnakAkuns' => fn($q4) => $q4->orderBy('kode_sub_anak_akun')
                                ->select(['sub_anak_akuns.id', 'id_anak_akun', 'kode_sub_anak_akun', 'nama_sub_anak_akun', 'saldo_normal']),
                        ]),
                ]),
        ])->whereNull('parent_id')->visible()->ordered()->get();
    }

    // ── PERUBAHAN 4: ATUR PARAMETER FUNGSI buildNeraca ───────────────────────
    // Menambahkan parameter 'array $m3 = []' di antara $qty dan $labaRugiBerjalan
    private function buildNeraca(Collection $groups, array $saldo, array $qty = [], array $m3 = [], float $labaRugiBerjalan = 0.0): array
    {
        $aktiva = ['sections' => [], 'total' => 0.0];
        $pasiva = ['sections' => [], 'total' => 0.0];

        foreach ($groups as $rootGroup) {
            $namaUpper = strtoupper(trim($rootGroup->nama));

            if ($rootGroup->childrenRecursive->isEmpty()) {
                // ── PERUBAHAN 5: TERUSKAN VARIABEL $m3 KE FUNCTION ANAK ─────────────────
                [$sections, $totalRoot] = $this->buildSectionsFromRoot($rootGroup, $saldo, $qty, $m3);
            } else {
                // ── PERUBAHAN 6: TERUSKAN VARIABEL $m3 KE FUNCTION REKURSIF ─────────────
                [$sections, $totalRoot] = $this->buildSections($rootGroup->childrenRecursive, $saldo, $qty, $m3);
            }

            $data = ['sections' => $sections, 'total' => $totalRoot];

            if (str_contains($namaUpper, 'AKTIVA')) {
                $aktiva = $data;
            } elseif (str_contains($namaUpper, 'PASIVA')) {
                $pasiva = $data;
            }
        }

        if ($labaRugiBerjalan != 0) {
            $pasiva['sections'][] = [
                'group'        => 'Laba Rugi Berjalan',
                'items'        => [[
                    'kode'  => '—',
                    'nama'  => 'Laba (Rugi) Periode Berjalan',
                    'nilai' => $labaRugiBerjalan,
                    'qty'   => null,
                    // ── PERUBAHAN 7: TAMBAHKAN KEY 'm3' UNTUK KONSISTENSI ARRAY ──────────
                    'm3'    => null,
                ]],
                'total'        => $labaRugiBerjalan,
                'sub_sections' => [],
            ];
            $pasiva['total'] += $labaRugiBerjalan;
        }

        return [
            'aktiva'      => $aktiva,
            'pasiva'      => $pasiva,
            'totalAktiva' => $aktiva['total'],
            'totalPasiva' => $pasiva['total'],
        ];
    }

    private function buildSectionsFromRoot(AkunGroup $rootGroup, array $saldo, array $qty = [], array $m3 = []): array
    {
        $items = [];
        $total = 0.0;

        $anakAkuns = $rootGroup->relationLoaded('anakAkuns')
            ? $rootGroup->anakAkuns
            : $rootGroup->anakAkuns()->orderBy('kode_anak_akun')->get();

        foreach ($anakAkuns as $anakAkun) {
            $nilaiAkun = $this->hitungNilaiAkun($anakAkun, $saldo);
            
            $q = 0.0;
            $vol = 0.0;
            $hasQty = false;
            $hasM3 = false;
            
            $subAkunList = [];
            if ($anakAkun->subAnakAkuns->isNotEmpty()) {
                foreach ($anakAkun->subAnakAkuns as $sub) {
                    $valSub = $saldo[$sub->kode_sub_anak_akun] ?? 0.0;
                    $qtySub = isset($qty[$sub->kode_sub_anak_akun]) ? (float) $qty[$sub->kode_sub_anak_akun] : null;
                    $m3Sub = isset($m3[$sub->kode_sub_anak_akun]) ? (float) $m3[$sub->kode_sub_anak_akun] : null;
                    $subAkunList[] = [
                        'kode' => $sub->kode_sub_anak_akun,
                        'nama' => $sub->nama_sub_anak_akun,
                        'nilai' => $valSub,
                        'qty' => $qtySub,
                        'm3' => $m3Sub,
                    ];
                    
                    if (isset($qty[$sub->kode_sub_anak_akun])) {
                        $q += (float) $qty[$sub->kode_sub_anak_akun];
                        $hasQty = true;
                    }
                    if (isset($m3[$sub->kode_sub_anak_akun])) {
                        $vol += (float) $m3[$sub->kode_sub_anak_akun];
                        $hasM3 = true;
                    }
                }
            } else {
                if (isset($qty[$anakAkun->kode_anak_akun])) {
                    $q = (float) $qty[$anakAkun->kode_anak_akun];
                    $hasQty = true;
                }
                if (isset($m3[$anakAkun->kode_anak_akun])) {
                    $vol = (float) $m3[$anakAkun->kode_anak_akun];
                    $hasM3 = true;
                }
            }

            $items[] = [
                'kode'  => $anakAkun->kode_anak_akun,
                'nama'  => $anakAkun->nama_anak_akun,
                'nilai' => $nilaiAkun,
                'qty'   => $hasQty ? $q : null,
                'm3'    => $hasM3 ? $vol : null,
                'sub_akuns' => $subAkunList,
            ];
            $total += $nilaiAkun;
        }

        return [[[
            'group'        => $rootGroup->nama,
            'items'        => $items,
            'total'        => $total,
            'sub_sections' => [],
        ]], $total];
    }

    // ── PERUBAHAN 10: SESUAIKAN SIGNATURE FUNGSI buildSections ───────────────
    // Menambahkan parameter 'array $m3 = []' di bagian akhir
    private function buildSections(Collection $groups, array $saldo, array $qty = [], array $m3 = []): array
    {
        $sections = [];
        $totalAll = 0.0;

        foreach ($groups as $group) {
            $isLeaf = $group->children->isEmpty();

            if ($isLeaf) {
                $items        = [];
                $totalSection = 0.0;

                foreach ($group->anakAkuns as $anakAkun) {
                    $nilaiAkun = $this->hitungNilaiAkun($anakAkun, $saldo);
                    
                    $q = 0.0;
                    $vol = 0.0;
                    $hasQty = false;
                    $hasM3 = false;
                    
                    $subAkunList = [];
                    if ($anakAkun->subAnakAkuns->isNotEmpty()) {
                        foreach ($anakAkun->subAnakAkuns as $sub) {
                            $valSub = $saldo[$sub->kode_sub_anak_akun] ?? 0.0;
                            $qtySub = isset($qty[$sub->kode_sub_anak_akun]) ? (float) $qty[$sub->kode_sub_anak_akun] : null;
                            $m3Sub = isset($m3[$sub->kode_sub_anak_akun]) ? (float) $m3[$sub->kode_sub_anak_akun] : null;
                            $subAkunList[] = [
                                'kode' => $sub->kode_sub_anak_akun,
                                'nama' => $sub->nama_sub_anak_akun,
                                'nilai' => $valSub,
                                'qty' => $qtySub,
                                'm3' => $m3Sub,
                            ];
                            
                            if (isset($qty[$sub->kode_sub_anak_akun])) {
                                $q += (float) $qty[$sub->kode_sub_anak_akun];
                                $hasQty = true;
                            }
                            if (isset($m3[$sub->kode_sub_anak_akun])) {
                                $vol += (float) $m3[$sub->kode_sub_anak_akun];
                                $hasM3 = true;
                            }
                        }
                    } else {
                        if (isset($qty[$anakAkun->kode_anak_akun])) {
                            $q = (float) $qty[$anakAkun->kode_anak_akun];
                            $hasQty = true;
                        }
                        if (isset($m3[$anakAkun->kode_anak_akun])) {
                            $vol = (float) $m3[$anakAkun->kode_anak_akun];
                            $hasM3 = true;
                        }
                    }

                    $items[] = [
                        'kode'  => $anakAkun->kode_anak_akun,
                        'nama'  => $anakAkun->nama_anak_akun,
                        'nilai' => $nilaiAkun,
                        'qty'   => $hasQty ? $q : null,
                        'm3'    => $hasM3 ? $vol : null,
                        'sub_akuns' => $subAkunList,
                    ];
                    $totalSection += $nilaiAkun;
                }

                $sections[] = [
                    'group'        => $group->nama,
                    'items'        => $items,
                    'total'        => $totalSection,
                    'sub_sections' => [],
                ];
                $totalAll += $totalSection;
            } else {
                // ── PERUBAHAN 12: TERUSKAN ARRAY $m3 KE METODE REKURSIF ANAK ──────────
                [$subSections, $totalBranch] = $this->buildSections($group->children, $saldo, $qty, $m3);
                $sections[] = [
                    'group'        => $group->nama,
                    'items'        => [],
                    'total'        => $totalBranch,
                    'sub_sections' => $subSections,
                ];
                $totalAll += $totalBranch;
            }
        }
        return [$sections, $totalAll];
    }

    private function hitungNilaiAkun($anakAkun, array $saldo): float
    {
        $total = 0.0;
        if ($anakAkun->subAnakAkuns->isNotEmpty()) {
            foreach ($anakAkun->subAnakAkuns as $sub) {
                $total += $saldo[$sub->kode_sub_anak_akun] ?? 0.0;
            }
        } elseif ($anakAkun->children->isNotEmpty()) {
            foreach ($anakAkun->children as $child) {
                $total += $this->hitungNilaiAkun($child, $saldo);
            }
        } else {
            $total = $saldo[$anakAkun->kode_anak_akun] ?? 0.0;
        }
        return $total;
    }
}
