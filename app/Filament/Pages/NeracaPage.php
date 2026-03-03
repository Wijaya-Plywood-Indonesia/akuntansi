<?php

namespace App\Filament\Pages;


// use App\Services\Filament\NeracaService;

use App\Filament\Services\NeracaService;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
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
    // Format: "YYYY-MM"  (mudah dibanding pisah tahun+bulan)
    public string $periodeAwal;
    public string $periodeAkhir;

    public function mount(): void
    {
        $now = now();
        // Default: 3 bulan terakhir
        $this->periodeAwal = $now->copy()->subMonths(2)->format('Y-m');
        $this->periodeAkhir = $now->format('Y-m');
    }

    // ── Computed ─────────────────────────────────────────────────────

    #[Computed]
    public function neracaMulti(): array
    {
        $periodeList = $this->buildPeriodeList();
        if (empty($periodeList))
            return [];

        return app(NeracaService::class)->hitungMulti($periodeList);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Bangun daftar periode dari periodeAwal s/d periodeAkhir secara kronologis.
     */
    public function buildPeriodeList(): array
    {
        try {
            $awal = Carbon::createFromFormat('Y-m', $this->periodeAwal)->startOfMonth();
            $akhir = Carbon::createFromFormat('Y-m', $this->periodeAkhir)->startOfMonth();
        } catch (\Exception $e) {
            return [];
        }

        // Guard: awal tidak boleh lebih dari akhir
        if ($awal->gt($akhir)) {
            return [];
        }

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

    /**
     * Opsi dropdown bulan — format "YYYY-MM" => "Januari 2026"
     * Range: 5 tahun ke belakang hingga 1 tahun ke depan.
     */
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

    /**
     * Cek apakah periodeAwal > periodeAkhir (untuk tampilkan pesan error)
     */
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