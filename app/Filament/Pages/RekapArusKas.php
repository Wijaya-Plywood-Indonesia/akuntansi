<?php

namespace App\Filament\Pages;

use App\Services\ArusKasService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class RekapArusKas extends Page
{
    use HasPageShield;

    // Jadi landing page (root panel) — lihat §8 dokumentasi.
    protected static ?string $slug = '/';

    protected static UnitEnum|string|null $navigationGroup = 'Jurnal & Akuntansi';

    protected static ?string $navigationLabel = 'Rekap Arus Kas';

    protected static ?string $title = 'Rekap Arus Kas';

    protected string $view = 'filament.pages.rekap-arus-kas';

    /** Preset aktif: kemarin | hari_ini | minggu_ini | bulan_ini | custom */
    public string $preset = 'hari_ini';

    public string $tanggalDari;

    public string $tanggalSampai;

    // Untuk input rentang custom (dua field terpisah dari yang sudah diterapkan)
    public string $customDariInput = '';

    public string $customSampaiInput = '';

    public ?string $rangeError = null;

    public array $hasil = [];

    public const MAKSIMAL_HARI_CUSTOM = 365;

    public function mount(): void
    {
        $this->terapkanPreset('hari_ini');
    }

    /** Label kategori => ikon Tabler untuk ditampilkan di UI. */
    public function ikonKategori(): array
    {
        return [
            'penjualan'           => 'ti-shopping-cart',
            'pembelian_stok'      => 'ti-truck-delivery',
            'produksi'            => 'ti-settings',
            'beban_usaha'         => 'ti-receipt',
            'pendanaan'           => 'ti-wallet',
            'lainnya'             => 'ti-dots',
            'transfer_internal'   => 'ti-arrows-left-right',
        ];
    }

    public function terapkanPreset(string $preset): void
    {
        $this->preset = $preset;
        $now = now();

        [$dari, $sampai] = match ($preset) {
            'kemarin'    => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'hari_ini'   => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'minggu_ini' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'bulan_ini'  => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            default      => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };

        $this->tanggalDari = $dari->format('Y-m-d');
        $this->tanggalSampai = $sampai->format('Y-m-d');
        $this->customDariInput = $this->tanggalDari;
        $this->customSampaiInput = $this->tanggalSampai;
        $this->rangeError = null;

        $this->generateLaporan();
    }

    public function terapkanRentangCustom(): void
    {
        $this->rangeError = null;

        if (blank($this->customDariInput) || blank($this->customSampaiInput)) {
            $this->rangeError = 'Isi tanggal dari dan sampai terlebih dahulu.';

            return;
        }

        try {
            $dari = Carbon::createFromFormat('Y-m-d', $this->customDariInput)->startOfDay();
            $sampai = Carbon::createFromFormat('Y-m-d', $this->customSampaiInput)->endOfDay();
        } catch (\Exception $e) {
            $this->rangeError = 'Format tanggal tidak valid.';

            return;
        }

        if ($dari->gt($sampai)) {
            $this->rangeError = 'Tanggal "dari" tidak boleh lebih besar dari "sampai".';

            return;
        }

        if ($dari->diffInDays($sampai) + 1 > self::MAKSIMAL_HARI_CUSTOM) {
            $this->rangeError = 'Maksimal rentang '.self::MAKSIMAL_HARI_CUSTOM.' hari (1 tahun) sekali tampil.';

            return;
        }

        $this->preset = 'custom';
        $this->tanggalDari = $dari->format('Y-m-d');
        $this->tanggalSampai = $sampai->format('Y-m-d');

        $this->generateLaporan();
    }

    protected function generateLaporan(): void
    {
        $start = Carbon::createFromFormat('Y-m-d', $this->tanggalDari)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $this->tanggalSampai)->endOfDay();

        $this->hasil = app(ArusKasService::class)->hitung($start, $end);
    }

    public function labelPeriode(): string
    {
        $dari = Carbon::createFromFormat('Y-m-d', $this->tanggalDari)->locale('id')->isoFormat('D MMM Y');
        $sampai = Carbon::createFromFormat('Y-m-d', $this->tanggalSampai)->locale('id')->isoFormat('D MMM Y');

        $label = match ($this->preset) {
            'kemarin'    => 'Kemarin',
            'hari_ini'   => 'Hari ini',
            'minggu_ini' => '7 hari terakhir',
            'bulan_ini'  => 'Bulan ini',
            default      => 'Rentang pilihan',
        };

        return $this->tanggalDari === $this->tanggalSampai
            ? "{$label} · {$dari}"
            : "{$label} · {$dari} - {$sampai}";
    }

    /** Persen perubahan saldo awal -> akhir, untuk bar "Alur kas bersih". */
    public function persenPerubahan(): float
    {
        $awal = (float) ($this->hasil['saldo_awal'] ?? 0);
        $akhir = (float) ($this->hasil['saldo_akhir'] ?? 0);

        if ($awal == 0.0) {
            return 0.0;
        }

        return round((($akhir - $awal) / $awal) * 100, 1);
    }

    /**
     * URL deep-link ke halaman Jurnal Umum, terisi otomatis untuk satu
     * nomor jurnal (lihat App\Filament\Pages\JurnalUmum::mount(), yang
     * membaca query string ?jurnal=... dan otomatis memfilter + scroll).
     */
    public function urlJurnal(int $noJurnal): string
    {
        return \App\Filament\Pages\JurnalUmum::getUrl(['jurnal' => $noJurnal]);
    }
}