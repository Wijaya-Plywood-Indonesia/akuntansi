<?php

namespace App\Filament\Pages;

use App\Services\ArusKasService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Pages\Page;
use UnitEnum;

class RekapArusKas extends Page
{
    use HasPageShield;

    // Slug '/' -> menjadikan halaman ini landing page panel (lihat AdminPanelProvider)
    protected static ?string $slug = '/';

    protected static string|UnitEnum|null $navigationGroup = 'Jurnal & Akuntansi';
    protected static ?string $title = 'Rekap Arus Kas';
    protected static ?string $navigationLabel = 'Rekap Arus Kas';
    protected static ?int $navigationSort = -10; // paling atas di menu

    protected string $view = 'filament.pages.rekap-arus-kas';

    public const MAX_RENTANG_HARI = 365;

    // ── Preset periode ──
    public string $periodeAktif = 'hari_ini';

    // ── Rentang tanggal custom ──
    public string $tglDariInput = '';
    public string $tglSampaiInput = '';
    public string $tglDari = '';
    public string $tglSampai = '';

    public ?string $errorRentang = null;

    // ── Hasil perhitungan ──
    public array $hasil = [];
    public string $labelPeriode = '';

    public function mount(): void
    {
        $this->terapkanPreset('hari_ini');
    }

    /**
     * Dipanggil dari tombol pintasan (Kemarin / Hari ini / 7 hari terakhir / Bulan ini).
     */
    public function terapkanPreset(string $preset): void
    {
        $this->periodeAktif = $preset;
        $this->errorRentang = null;
        $now = now();

        [$start, $end, $label] = match ($preset) {
            'kemarin'     => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), 'Kemarin'],
            'minggu_ini'  => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), '7 hari terakhir'],
            'bulan_ini'   => [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'Bulan ini'],
            default       => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Hari ini'],
        };

        $this->tglDari = $start->format('Y-m-d');
        $this->tglSampai = $end->format('Y-m-d');
        $this->tglDariInput = $this->tglDari;
        $this->tglSampaiInput = $this->tglSampai;

        $this->hitungRekap($start, $end, $label);
    }

    /**
     * Dipanggil dari tombol "Terapkan" pada input rentang tanggal custom.
     */
    public function terapkanRentangCustom(): void
    {
        $this->errorRentang = null;

        if (blank($this->tglDariInput) || blank($this->tglSampaiInput)) {
            $this->errorRentang = 'Isi tanggal dari dan sampai terlebih dahulu.';
            return;
        }

        $start = Carbon::parse($this->tglDariInput)->startOfDay();
        $end = Carbon::parse($this->tglSampaiInput)->endOfDay();

        if ($start->gt($end)) {
            $this->errorRentang = 'Tanggal "dari" tidak boleh lebih besar dari "sampai".';
            return;
        }

        if ($start->diffInDays($end) + 1 > self::MAX_RENTANG_HARI) {
            $this->errorRentang = 'Maksimal rentang ' . self::MAX_RENTANG_HARI . ' hari (1 tahun) sekali tampil.';
            return;
        }

        $this->periodeAktif = 'custom';
        $this->tglDari = $start->format('Y-m-d');
        $this->tglSampai = $end->format('Y-m-d');

        $label = $start->isSameDay($end)
            ? $start->translatedFormat('d F Y')
            : $start->translatedFormat('d M Y') . ' - ' . $end->translatedFormat('d M Y');

        $this->hitungRekap($start, $end, $label);
    }

    private function hitungRekap(Carbon $start, Carbon $end, string $label): void
    {
        $this->labelPeriode = $label;
        $this->hasil = app(ArusKasService::class)->hitung($start, $end);
    }

    /**
     * URL menuju Jurnal Umum yang HANYA menampilkan satu nomor jurnal
     * terkait (dipakai oleh tombol "Lihat di Jurnal" di view) — langsung
     * ke tabel history, bukan ke form input di atas.
     */
    public function urlJurnal(string $noJurnal): string
    {
        $query = http_build_query([
            'filterJurnalNomor' => $noJurnal,
        ]);

        return JurnalUmum::getUrl() . '?' . $query . '#riwayat-jurnal';
    }
}