<?php

namespace App\Filament\Pages;

use App\Filament\Pages\JurnalUmum;
use App\Services\ArusKasPerAkunService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use UnitEnum;

class RekapArusKasV2 extends Page
{
    use HasPageShield;

    // Slug '/' -> menjadikan halaman ini landing page panel (lihat AdminPanelProvider)
    protected static ?string $slug = '/';

    protected static string|UnitEnum|null $navigationGroup = 'Jurnal & Akuntansi';
    protected static ?string $title = 'Arus Kas';
    protected static ?string $navigationLabel = 'Arus Kas';
    protected static ?int $navigationSort = -10; // paling atas di menu

    protected string $view = 'filament.pages.rekap-arus-kas-v2';

    public const MAX_RENTANG_HARI = 365;
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    // ── Preset periode ── (default per hari, sesuai poin 1)
    public string $periodeAktif = 'hari_ini';

    public string $tglDariInput = '';
    public string $tglSampaiInput = '';
    public string $tglDari = '';
    public string $tglSampai = '';

    public ?string $errorRentang = null;

    // ── Pilihan akun kas/bank ── (poin 2 & 3)
    public array $daftarAkunKas = [];      // kode => nama, semua akun kas/bank yang ada
    public array $akunTerpilih = [];       // kode akun yang dicentang user

    // ── Hasil perhitungan ──
    public array $hasil = [];
    public string $labelPeriode = '';

    public function mount(): void
    {
        $service = app(ArusKasPerAkunService::class);
        $this->daftarAkunKas = $service->getDaftarAkunKas();

        // Default: hanya akun yang "ada isinya" (saldo terkini tidak nol)
        // yang tercentang, biar direksi tidak dipusingkan rekening yang
        // memang belum/tidak dipakai. Kalau ternyata semua nol (data baru),
        // fallback centang semua supaya halaman tidak kosong melompong.
        $saldoSekarang = $service->hitungSaldoSekarang(array_keys($this->daftarAkunKas));
        $akunBerisi = array_keys(array_filter($saldoSekarang, fn($s) => abs($s) > 0.01));

        $this->akunTerpilih = !empty($akunBerisi) ? $akunBerisi : array_keys($this->daftarAkunKas);

        $this->terapkanPreset('hari_ini');
    }

    /**
     * Dipanggil otomatis oleh Livewire tiap kali checkbox akun
     * dicentang/dilepas (wire:model.live) — langsung terapkan tanpa
     * perlu tombol "Tampilkan" lagi.
     */
    public function updatedAkunTerpilih(): void
    {
        $this->terapkanAkunTerpilih();
    }

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

    /**
     * Dipanggil dari tombol "Terapkan" di panel pilihan akun kas/bank.
     */
    public function terapkanAkunTerpilih(): void
    {
        $start = Carbon::parse($this->tglDari)->startOfDay();
        $end = Carbon::parse($this->tglSampai)->endOfDay();

        $this->hitungRekap($start, $end, $this->labelPeriode);
    }

    public function pilihSemuaAkun(): void
    {
        $this->akunTerpilih = array_keys($this->daftarAkunKas);
        $this->terapkanAkunTerpilih();
    }

    public function kosongkanAkun(): void
    {
        $this->akunTerpilih = [];
        $this->terapkanAkunTerpilih();
    }

    private function hitungRekap(Carbon $start, Carbon $end, string $label): void
    {
        $this->labelPeriode = $label;

        // Pertahankan urutan sesuai daftarAkunKas biar kolom stabil,
        // bukan urutan klik user.
        $kodeTerpilih = array_values(array_intersect(array_keys($this->daftarAkunKas), $this->akunTerpilih));

        $this->hasil = app(ArusKasPerAkunService::class)->hitung($start, $end, $kodeTerpilih);
    }

    public function urlJurnal(string $noJurnal): string
    {
        $query = http_build_query([
            'filterJurnalNomor' => $noJurnal,
        ]);

        return JurnalUmum::getUrl() . '?' . $query . '#riwayat-jurnal';
    }
}