<?php

namespace App\Filament\Services;

use App\Models\AkunGroup;
use App\Models\BukuBesar;
use App\Models\JurnalUmum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class NeracaService
{
    /**
     * Hitung neraca untuk banyak periode.
     *
     * @param  array  $periodeList  [ ['tahun'=>2025,'bulan'=>12], ['tahun'=>2026,'bulan'=>1], ... ]
     * @return array  Keyed by "YYYY-MM" => [ label, aktiva, pasiva, totalAktiva, totalPasiva ]
     */
    public function hitungMulti(array $periodeList): array
    {
        if (empty($periodeList)) {
            return [];
        }

        // 1. Load struktur COA sekali saja
        $groups = $this->loadGroups();

        // 2. Hitung neraca per periode
        $result = [];

        foreach ($periodeList as $periode) {
            $tahun = (int) $periode['tahun'];
            $bulan = (int) $periode['bulan'];

            $tglAwal = Carbon::create($tahun, $bulan, 1)->startOfMonth();
            $tglAkhir = Carbon::create($tahun, $bulan, 1)->endOfMonth();

            // a. Saldo awal dari buku_besar (langsung bulan ini)
            $saldoAwal = $this->getSaldoAwal($tahun, $bulan);

            // b. Mutasi dari jurnal_umums dalam bulan ini saja
            $mutasi = $this->getMutasi($tglAwal, $tglAkhir);

            // c. Gabungkan: saldo tampil = saldo awal + mutasi
            $saldoFinal = $this->gabungSaldo($saldoAwal, $mutasi);

            $key = $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT);
            $label = Carbon::create($tahun, $bulan)->translatedFormat('F Y');

            $result[$key] = array_merge(
                ['label' => $label, 'tahun' => $tahun, 'bulan' => $bulan],
                $this->buildNeraca($groups, $saldoFinal)
            );
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Load semua akun_groups + relasi COA — hanya sekali.
     */
    private function loadGroups(): Collection
    {
        return AkunGroup::with([
            'children' => fn($q) => $q->ordered()->with([
                'anakAkuns' => fn($q2) => $q2
                    ->aktif()
                    ->orderBy('kode_anak_akun')
                    ->with('subAnakAkuns:id,id_anak_akun,kode_sub_anak_akun,saldo_normal'),
            ]),
        ])
            ->whereNull('parent_id')
            ->visible()
            ->ordered()
            ->get();
    }

    /**
     * Ambil saldo awal dari tabel buku_besar.
     *
     * Konvensi data:
     *   buku_besar.bulan = N  →  saldo awal untuk bulan N+1
     *   Contoh: record bulan=2 (Feb) = saldo awal untuk MARET
     *
     * Jadi neraca bulan X → query buku_besar WHERE bulan = X-1.
     * Edge case Januari ditangani otomatis oleh Carbon::subMonth().
     *
     * Return: [ 'no_akun' => float ]
     */
    private function getSaldoAwal(int $tahun, int $bulan): array
    {
        // Mundur 1 bulan — Carbon otomatis tangani Januari → Desember tahun lalu
        $prev = Carbon::create($tahun, $bulan, 1)->subMonth();

        $rows = BukuBesar::query()
            ->where('tahun', $prev->year)
            ->where('bulan', $prev->month)
            ->get(['no_akun', 'saldo']);

        $saldo = [];
        foreach ($rows as $row) {
            $saldo[$row->no_akun] = (float) $row->saldo;
        }

        return $saldo;
    }

    /**
     * Ambil mutasi debet/kredit dari jurnal_umums dalam rentang bulan.
     * Return: [ 'kode_sub_anak_akun' => net_mutasi_float ]
     * Net mutasi = D - K (positif jika lebih banyak debet)
     */
    private function getMutasi(Carbon $dari, Carbon $sampai): array
    {
        $rows = JurnalUmum::query()
            ->whereBetween('tgl', [$dari->toDateString(), $sampai->toDateString()])
            ->selectRaw('no_akun, map, SUM(banyak * harga) AS total')
            ->groupBy('no_akun', 'map')
            ->get();

        $mutasi = [];
        foreach ($rows as $row) {
            $kode = $row->no_akun;
            $map = strtolower($row->map);
            $nilai = (float) $row->total;

            $mutasi[$kode] = ($mutasi[$kode] ?? 0) + ($map === 'd' ? $nilai : -$nilai);
        }

        return $mutasi;
    }

    /**
     * Gabungkan saldo awal + mutasi.
     * Kunci dari buku_besar bisa berupa kode anak_akun ('110') atau sub_anak_akun ('110-01').
     * Kunci dari jurnal_umums selalu kode sub_anak_akun ('110-01').
     *
     * Return: [ 'no_akun' => float ]  — union dari kedua sumber
     */
    private function gabungSaldo(array $saldoAwal, array $mutasi): array
    {
        $gabungan = $saldoAwal; // mulai dari saldo awal

        foreach ($mutasi as $kode => $nilaiMutasi) {
            $gabungan[$kode] = ($gabungan[$kode] ?? 0) + $nilaiMutasi;
        }

        return $gabungan;
    }

    /**
     * Build struktur neraca Aktiva / Pasiva dari saldo gabungan.
     *
     * Pencocokan saldo ke akun:
     * - Cek $saldo[$sub->kode_sub_anak_akun]  (prioritas: kode sub_anak_akun persis)
     * - Fallback: cek $saldo[$anakAkun->kode_anak_akun] (kode anak_akun)
     */
    private function buildNeraca(Collection $groups, array $saldo): array
    {
        $aktiva = ['sections' => [], 'total' => 0];
        $pasiva = ['sections' => [], 'total' => 0];

        foreach ($groups as $rootGroup) {
            $sections = [];
            $totalRoot = 0;

            foreach ($rootGroup->children as $childGroup) {
                $items = [];
                $totalSection = 0;

                foreach ($childGroup->anakAkuns as $anakAkun) {
                    $nilaiAkun = 0;

                    if ($anakAkun->subAnakAkuns->isNotEmpty()) {
                        // Hitung dari sub_anak_akun
                        foreach ($anakAkun->subAnakAkuns as $sub) {
                            $saldoNormal = strtolower(
                                $sub->saldo_normal ?? $anakAkun->saldo_normal ?? 'debet'
                            );

                            // Prioritas: cari dengan kode sub dulu, fallback ke kode anak
                            $nilaiRaw = $saldo[$sub->kode_sub_anak_akun]
                                ?? $saldo[$anakAkun->kode_anak_akun]
                                ?? 0;

                            // Flip untuk akun saldo normal kredit
                            $nilaiAkun += ($saldoNormal === 'kredit') ? -$nilaiRaw : $nilaiRaw;
                        }
                    } else {
                        // Tidak punya sub, langsung pakai kode anak_akun
                        $saldoNormal = strtolower($anakAkun->saldo_normal ?? 'debet');
                        $nilaiRaw = $saldo[$anakAkun->kode_anak_akun] ?? 0;
                        $nilaiAkun = ($saldoNormal === 'kredit') ? -$nilaiRaw : $nilaiRaw;
                    }

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

                $totalRoot += $totalSection;
            }

            $data = ['sections' => $sections, 'total' => $totalRoot];

            if (strtoupper($rootGroup->nama) === 'AKTIVA') {
                $aktiva = $data;
            } else {
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
}