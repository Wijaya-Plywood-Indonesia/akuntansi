<?php

namespace App\Filament\Pages;

use App\Models\IndukAkun;
use App\Models\JurnalUmum;
use Filament\Pages\Page;
use Carbon\Carbon;
use BackedEnum;
use UnitEnum;
use Illuminate\Support\Facades\DB;
use Throwable;

class BukuBesar extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected string $view = 'filament.pages.buku-besar';
    protected static ?string $navigationLabel = 'Buku Besar';
    protected static ?string $title = 'Buku Besar';

    public $indukAkuns = [];
    public $filterBulan;
    public $isLoading = true;
    public $saldoMap = [];
    public $saldoAwalMap = [];

    public function mount()
    {
        $this->filterBulan = Carbon::now()->format('Y-m'); // Default bulan ini
        $this->loadData();
    }

    public function initLoad()
    {
        $this->loadData();
        $this->preloadSaldoAwal();
        $this->preloadSaldo();
        $this->isLoading = false;
    }

    public function updatedFilterBulan()
{
    $this->saldoAwalMap = [];
    $this->saldoMap = [];

    $this->preloadSaldoAwal();
    $this->preloadSaldo();
    $this->loadData();
}

    private function preloadSaldoAwal()
    {
        $date = Carbon::parse($this->filterBulan)->subMonth();

        $this->saldoAwalMap = DB::table('buku_besar')
            ->where('tahun', $date->year)
            ->where('bulan', $date->month)
            ->pluck('saldo', 'no_akun')
            ->toArray();
    }

    private function preloadSaldo()
    {
        $start = Carbon::parse($this->filterBulan)->startOfMonth();
        $end   = Carbon::parse($this->filterBulan)->endOfMonth();

        $this->saldoMap = JurnalUmum::whereBetween('tgl', [$start, $end])
            ->selectRaw("
            no_akun,
            SUM(
                CASE 
                    WHEN LOWER(map) = 'd'
                    THEN COALESCE(banyak * harga, harga, 0)
                    ELSE -COALESCE(banyak * harga, harga, 0)
                END
            ) as total
        ")
            ->groupBy('no_akun')
            ->pluck('total', 'no_akun')
            ->toArray();
    }
    public function loadData()
    {
        $this->indukAkuns = IndukAkun::with([
            'anakAkuns' => function ($query) {
                $query->whereNull('parent')
                    ->with([
                        'children.children', // rekursif 2 level
                        'subAnakAkuns'
                    ]);
            }
        ])->get();
    }

    // Fungsi menghitung nominal satu baris transaksi
    private function hitungNominal($trx)
    {
        $mode = strtolower($trx->hit_kbk ?? '');

        // Jika data lama (hit_kbk null/kosong)
        if ($mode === '' || $mode === null) {
            return $trx->harga ?? 0;
        }

        // Jika banyak
        if ($mode === 'b' || $mode === 'banyak') {
            return ($trx->banyak ?? 0) * ($trx->harga ?? 0);
        }

        // Jika kubikasi
        return ($trx->m3 ?? 0) * ($trx->harga ?? 0);
    }

    // Mendapatkan Saldo Awal (Transaksi sebelum bulan filter)
    public function getSaldoAwal($kode)
{
    return $this->saldoAwalMap[$kode] ?? 0;
}

    public function getSaldoBulan($kode)
{
    return $this->saldoMap[$kode] ?? 0;
}

    // Transaksi hanya di bulan terpilih
    public function getTransaksiByKode($kode)
    {
        $start = Carbon::parse($this->filterBulan)->startOfMonth();
        $end = Carbon::parse($this->filterBulan)->endOfMonth();

        return JurnalUmum::where('no_akun', $kode)
            ->whereBetween('tgl', [$start, $end])
            ->orderBy('tgl', 'asc')    // Urutkan Tanggal dulu
            ->orderBy('jurnal', 'asc')
            ->get();
    }

    // Perbaikan Saldo Akun (Mendukung rekursif untuk Induk)
    public function getTotalRecursive($akun)
    {
        $total = 0;

        $kode =
            $akun->kode_anak_akun
            ?? $akun->kode_sub_anak_akun
            ?? null;

        if ($kode) {
            $total += $this->saldoAwalMap[$kode] ?? 0;
            $total += $this->saldoMap[$kode] ?? 0;
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
