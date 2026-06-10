<?php

namespace App\Exports;

use App\Models\IndukAkun;
use App\Models\JurnalUmum;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BukuBesarExport implements FromView, ShouldAutoSize
{
    protected string $filterBulan;
    protected array $saldoMap = [];
    protected array $saldoAwalMap = [];

    public function __construct(string $filterBulan)
    {
        $this->filterBulan = $filterBulan;
    }

    public function view(): View
    {
        $this->preloadSaldoAwal();
        $this->preloadSaldo();

        $indukAkuns = IndukAkun::with([
            'anakAkuns' => function ($query) {
                $query->whereNull('parent')
                    ->with([
                        'subAnakAkuns',
                        'children' => function ($q) {
                            $q->with([
                                'subAnakAkuns',
                                'children' => function ($q2) {
                                    $q2->with(['subAnakAkuns']);
                                },
                            ]);
                        },
                    ]);
            },
        ])->get();

        return view('exports.buku-besar', [
            'indukAkuns' => $indukAkuns,
            'filterBulan' => $this->filterBulan,
            'saldoMap' => $this->saldoMap,
            'saldoAwalMap' => $this->saldoAwalMap,
            'exporter' => $this,
        ]);
    }

    private function preloadSaldoAwal(): void
    {
        $date = Carbon::parse($this->filterBulan)->subMonth();

        $this->saldoAwalMap = DB::table('buku_besar')
            ->where('tahun', $date->year)
            ->where('bulan', $date->month)
            ->pluck('saldo', 'no_akun')
            ->toArray();
    }

    private function preloadSaldo(): void
    {
        $start = Carbon::parse($this->filterBulan)->startOfMonth();
        $end   = Carbon::parse($this->filterBulan)->endOfMonth();

        $rows = JurnalUmum::whereBetween('tgl', [$start, $end])
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
            ->get();

        $this->saldoMap = [];
        foreach ($rows as $row) {
            $this->saldoMap[$row->no_akun] = [
                'd' => (float) $row->total_debit,
                'k' => (float) $row->total_kredit,
            ];
        }
    }

    public function hitungNominal($trx): float
    {
        return match (strtolower($trx->hit_kbk ?? '')) {
            'b'     => (float) ($trx->banyak ?? 0) * (float) ($trx->harga ?? 0),
            'm'     => (float) ($trx->m3 ?? 0)     * (float) ($trx->harga ?? 0),
            default => (float) ($trx->harga ?? 0),
        };
    }

    public function getSaldoAwal(string $kode): float
    {
        return (float) ($this->saldoAwalMap[$kode] ?? 0);
    }

    public function getSaldoBulan(string $kode): float
    {
        return (float) ($this->saldoMap[$kode]['d'] ?? 0)
             - (float) ($this->saldoMap[$kode]['k'] ?? 0);
    }

    public function getTransaksiByKode(string $kode)
    {
        $start = Carbon::parse($this->filterBulan)->startOfMonth();
        $end   = Carbon::parse($this->filterBulan)->endOfMonth();

        return JurnalUmum::where('no_akun', $kode)
            ->whereBetween('tgl', [$start, $end])
            ->orderBy('tgl', 'asc')
            ->orderBy('jurnal', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    public function getTotalRecursive($akun): float
    {
        $total = 0.0;

        $kode = $akun->kode_anak_akun ?? $akun->kode_sub_anak_akun ?? null;

        if ($kode && isset($this->saldoMap[$kode])) {
            $saldoAwal   = (float) ($this->saldoAwalMap[$kode] ?? 0);
            $debit       = (float) ($this->saldoMap[$kode]['d'] ?? 0);
            $kredit      = (float) ($this->saldoMap[$kode]['k'] ?? 0);

            $saldoNormal = strtolower($akun->saldo_normal ?? 'debit');
            $isKredit    = in_array($saldoNormal, ['kredit', 'credit', 'k']);

            if ($isKredit) {
                $total += $saldoAwal + $kredit - $debit;
            } else {
                $total += $saldoAwal + $debit - $kredit;
            }
        } elseif ($kode) {
            $total += (float) ($this->saldoAwalMap[$kode] ?? 0);
        }

        if (isset($akun->children)) {
            foreach ($akun->children as $child) {
                $total += $this->getTotalRecursive($child);
            }
        }

        if (isset($akun->subAnakAkuns)) {
            foreach ($akun->subAnakAkuns as $sub) {
                $total += $this->getTotalRecursive($sub);
            }
        }

        return $total;
    }
}
