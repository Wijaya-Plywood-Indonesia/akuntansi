<?php

namespace App\Filament\Pages;

use App\Models\Penjualan;
use App\Models\RekeningPerusahaan;
use App\Services\PenjualanPelunasanService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use UnitEnum;

class PelunasanPenjualan extends Page
{
    use HasPageShield;

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected string $view = 'filament.pages.pelunasan-penjualan';

    protected static ?string $navigationLabel = 'Pelunasan';

    protected static ?string $title = 'Pelunasan Penjualan';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    public ?string $search = '';

    public ?int $penjualan_id = null;

    public ?Penjualan $selectedNota = null;

    // Payment fields
    public string $metode_pembayaran = 'TUNAI';

    public ?int $rekening_perusahaan_id = null;

    public int $nominal = 0;

    public int $nominal_tunai = 0;

    public int $nominal_transfer = 0;

    public ?string $keterangan = '';

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    #[Computed]
    public function notaResults(): Collection
    {
        return app(PenjualanPelunasanService::class)->getBelumLunas($this->search, 20);
    }

    #[Computed]
    public function rekeningPerusahaan(): Collection
    {
        return RekeningPerusahaan::all();
    }

    #[Computed]
    public function selectedBank(): ?RekeningPerusahaan
    {
        if (! $this->rekening_perusahaan_id) {
            return null;
        }

        return RekeningPerusahaan::find($this->rekening_perusahaan_id);
    }

    public function pilihNota(int $id): void
    {
        $nota = Penjualan::find($id);

        if (! $nota) {
            Notification::make()
                ->title('Error')
                ->body('Nota tidak ditemukan.')
                ->danger()
                ->send();

            return;
        }

        $service = app(PenjualanPelunasanService::class);

        if (! $service->bisaDilunasi($nota)) {
            Notification::make()
                ->title('Tidak Bisa Diproses')
                ->body('Nota ini bukan jenis COD/DP atau sudah lunas.')
                ->warning()
                ->send();

            return;
        }

        $this->penjualan_id = $id;
        $this->selectedNota = $nota;
        $this->nominal = $service->sisaTagihan($nota);
        $this->nominal_tunai = 0;
        $this->nominal_transfer = 0;
        $this->rekening_perusahaan_id = null;
        $this->keterangan = '';
        $this->metode_pembayaran = self::_defaultMetode();
    }

    public function batalPilihNota(): void
    {
        $this->penjualan_id = null;
        $this->selectedNota = null;
        $this->nominal = 0;
        $this->nominal_tunai = 0;
        $this->nominal_transfer = 0;
        $this->rekening_perusahaan_id = null;
        $this->keterangan = '';
        $this->metode_pembayaran = self::_defaultMetode();
    }

    public function getSisa(): int
    {
        if (! $this->selectedNota) {
            return 0;
        }

        return app(PenjualanPelunasanService::class)->sisaTagihan($this->selectedNota);
    }

    public function setNominal(string $type): void
    {
        if ($type === 'pas' && $this->selectedNota) {
            $this->nominal = $this->getSisa();
        }
    }

    public function simpanPelunasan(): void
    {
        if (! $this->selectedNota) {
            Notification::make()
                ->title('Error')
                ->body('Silakan pilih nota terlebih dahulu.')
                ->danger()
                ->send();

            return;
        }

        $service = app(PenjualanPelunasanService::class);

        try {
            $service->prosesPelunasan($this->selectedNota, [
                'metode_pembayaran' => $this->metode_pembayaran,
                'nominal' => $this->nominal,
                'nominal_tunai' => $this->nominal_tunai,
                'nominal_transfer' => $this->nominal_transfer,
                'rekening_perusahaan_id' => $this->rekening_perusahaan_id,
                'keterangan' => $this->keterangan,
            ]);

            Notification::make()
                ->title('Sukses')
                ->body('Pelunasan berhasil disimpan.')
                ->success()
                ->send();

            // Refresh selected note or reset if fully paid
            $fresh = $this->selectedNota->fresh();
            if ($service->sisaTagihan($fresh) <= 0) {
                $this->batalPilihNota();
            } else {
                $this->pilihNota($fresh->id);
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getViewData(): array
    {
        return [
            'notaResults' => $this->notaResults,
            'rekeningPerusahaan' => $this->rekeningPerusahaan,
            'selectedBank' => $this->selectedBank,
        ];
    }

    private static function _defaultMetode(): string
    {
        return PenjualanPelunasanService::METODE_TUNAI;
    }
}
