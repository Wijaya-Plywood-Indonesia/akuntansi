<?php

namespace App\Filament\Pages;

use App\Filament\Services\NeracaService;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Attributes\Computed;
use UnitEnum;

class NeracaPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Neraca';
    protected static UnitEnum|string|null $navigationGroup = 'Jurnal';
    protected static ?string $title = 'Neraca';
    protected string $view = 'filament.pages.neraca-page';

    // ── Filter state ──────────────────────────────────────────────────
    public int $tahun;
    public int $bulanAwal;
    public int $bulanAkhir;

    public function mount(): void
    {
        $now = now();
        $this->tahun = $now->year;
        $this->bulanAwal = 1;
        $this->bulanAkhir = $now->month;
    }

    // ── Data kalkulasi (reactive) ──────────────────────────────────────
    #[Computed]
    public function neraca(): array
    {
        return app(NeracaService::class)->hitung(
            $this->tahun,
            $this->bulanAwal,
            $this->bulanAkhir
        );
    }

    // ── Helpers untuk view ────────────────────────────────────────────
    public function namaBulan(int $bulan): string
    {
        return \Carbon\Carbon::create(null, $bulan)->translatedFormat('F');
    }

    public function listTahun(): array
    {
        $tahunSekarang = now()->year;
        return array_combine(
            range($tahunSekarang - 4, $tahunSekarang),
            range($tahunSekarang - 4, $tahunSekarang)
        );
    }

    public function listBulan(): array
    {
        $bulan = [];
        for ($i = 1; $i <= 12; $i++) {
            $bulan[$i] = \Carbon\Carbon::create(null, $i)->translatedFormat('F');
        }
        return $bulan;
    }
}