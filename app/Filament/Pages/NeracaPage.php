<?php

namespace App\Filament\Pages;

use App\Filament\Services\NeracaService;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Attributes\Computed;
use Carbon\Carbon;
use UnitEnum;

class NeracaPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Neraca';
    protected static UnitEnum|string|null $navigationGroup = 'Jurnal';
    protected static ?string $title = 'Neraca';
    protected string $view = 'filament.pages.neraca-page';

    // ── Filter state ─────────────────────────────────────────────────
    public string $periodeAwal;
    public string $periodeAkhir;

    public function mount(): void
    {
        $now = now();
        $this->periodeAwal = $now->copy()->subMonths(2)->format('Y-m');
        $this->periodeAkhir = $now->format('Y-m');
    }

    // ── Computed ─────────────────────────────────────────────────────

    /**
     * Hasil neraca multi-periode.
     * Data diambil dari tabel buku_besar (saldo akhir bulan),
     * bukan langsung dari jurnal_umums.
     */
    #[Computed]
    public function neracaMulti(): array
    {
        $periodeList = $this->buildPeriodeList();
        if (empty($periodeList))
            return [];

        return app(NeracaService::class)->hitungMulti($periodeList);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function buildPeriodeList(): array
    {
        try {
            $awal = Carbon::createFromFormat('Y-m', $this->periodeAwal)->startOfMonth();
            $akhir = Carbon::createFromFormat('Y-m', $this->periodeAkhir)->startOfMonth();
        } catch (\Exception $e) {
            return [];
        }

        if ($awal->gt($akhir))
            return [];

        // Guard: maksimal 12 bulan
        if ($awal->diffInMonths($akhir) > 11) {
            $akhir = $awal->copy()->addMonths(11);
        }

        $list = [];
        $current = $awal->copy();

        while ($current->lte($akhir)) {
            $list[] = [
                'tahun' => (int) $current->format('Y'),
                'bulan' => (int) $current->format('n'),
            ];
            $current->addMonth();
        }

        return $list;
    }

    public function opsiPeriode(): array
    {
        $start = now()->subYears(5)->startOfYear();
        $end = now()->addYear()->endOfYear();
        $opsi = [];

        $current = $start->copy();
        while ($current->lte($end)) {
            $key = $current->format('Y-m');
            $opsi[$key] = $current->translatedFormat('F Y');
            $current->addMonth();
        }

        return $opsi;
    }

    public function jumlahPeriode(): int
    {
        return count($this->buildPeriodeList());
    }

    public function periodeValid(): bool
    {
        try {
            $awal = Carbon::createFromFormat('Y-m', $this->periodeAwal);
            $akhir = Carbon::createFromFormat('Y-m', $this->periodeAkhir);
            return $awal->lte($akhir);
        } catch (\Exception $e) {
            return false;
        }
    }
}