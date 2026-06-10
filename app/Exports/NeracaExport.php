<?php

namespace App\Exports;

use App\Services\NeracaService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class NeracaExport implements FromView, ShouldAutoSize
{
    protected array $periodeList;
    protected bool $tampilkanSaldoNol;

    public function __construct(array $periodeList, bool $tampilkanSaldoNol = false)
    {
        $this->periodeList = $periodeList;
        $this->tampilkanSaldoNol = $tampilkanSaldoNol;
    }

    public function view(): View
    {
        $jenisFilter = (isset($this->periodeList[0]) && strlen($this->periodeList[0]['date_string']) === 10) ? 'hari' : 'bulan';
        
        $neracaMulti = app(NeracaService::class)->hitungMulti($this->periodeList, $jenisFilter);

        return view('exports.neraca', [
            'neracaMulti' => $neracaMulti,
            'jenisFilter' => $jenisFilter,
            'tampilkanSaldoNol' => $this->tampilkanSaldoNol,
            'exporter' => $this,
        ]);
    }
}
