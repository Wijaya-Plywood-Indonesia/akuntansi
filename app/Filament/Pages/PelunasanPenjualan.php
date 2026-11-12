<?php

namespace App\Filament\Pages;

use App\Models\Penjualan;
use App\Models\RekeningPerusahaan;
use App\Models\SubAnakAkun;
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

    protected static ?string $navigationLabel = 'Pelunasan Penjualan';

    protected static ?string $title = 'Pelunasan Penjualan';

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    public ?string $search = '';

    public ?int $penjualan_id = null;

    public ?Penjualan $selectedNota = null;

    // ── Mode: 'PELUNASAN' | 'SPLIT' ──────────────────────────────────────────
    public string $mode_ui = 'PELUNASAN';

    // ── PELUNASAN: 1 akun dipilih dari 1101.x (kecuali .0) ──────────────────
    // sub_anak_akun_id untuk slot tunggal
    public ?int $akun_pelunasan_id = null;

    public int $nominal = 0;

    // ── SPLIT: 2 slot, masing-masing pilih akun + nominal ───────────────────
    public ?int $akun_split_a_id = null;

    public int $nominal_split_a = 0;

    public ?int $akun_split_b_id = null;

    public int $nominal_split_b = 0;

    // ── Legacy / internal (dipakai PenjualanPelunasanService) ───────────────
    public string $metode_pembayaran = 'TUNAI';

    public ?int $rekening_perusahaan_id = null;

    public int $nominal_tunai = 0;

    public int $nominal_transfer = 0;

    public ?string $keterangan = '';

    public ?string $tanggal_pelunasan = null;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    // ── Kas tunai: akun 1101.1 dan 1101.2 (tidak punya RekeningPerusahaan) ──
    private const KODE_KAS_TUNAI = ['1101.1', '1101.2'];

    #[Computed]
    public function notaResults(): Collection
    {
        return app(PenjualanPelunasanService::class)->getBelumLunas($this->search, 20);
    }

    /** Semua SubAnakAkun 1101.x kecuali .0, diurutkan. */
    #[Computed]
    public function akunKasList(): Collection
    {
        return SubAnakAkun::where('kode_sub_anak_akun', 'like', '1101.%')
            ->where('kode_sub_anak_akun', '!=', '1101.0')
            ->orderBy('kode_sub_anak_akun')
            ->get();
    }

    /** RekeningPerusahaan yang terhubung ke SubAnakAkun tertentu. */
    #[Computed]
    public function rekeningPerSubAkun(): Collection
    {
        return RekeningPerusahaan::with('subAnakAkun')->get();
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

    /** Ambil RekeningPerusahaan yang terhubung ke SubAnakAkun id tertentu (null jika kas tunai). */
    public function getRekeningUntukAkun(?int $akunId): ?RekeningPerusahaan
    {
        if (! $akunId) {
            return null;
        }

        return $this->rekeningPerSubAkun->firstWhere('sub_anak_akun_id', $akunId);
    }

    /** Apakah akun (by id) termasuk kas tunai (1101.1 / 1101.2)? */
    public function isKasTunai(?int $akunId): bool
    {
        if (! $akunId) {
            return false;
        }

        $akun = $this->akunKasList->firstWhere('id', $akunId);

        return $akun && in_array($akun->kode_sub_anak_akun, self::KODE_KAS_TUNAI, true);
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
        $this->nominal_split_a = 0;
        $this->nominal_split_b = 0;
        $this->akun_pelunasan_id = null;
        $this->akun_split_a_id = null;
        $this->akun_split_b_id = null;
        $this->rekening_perusahaan_id = null;
        $this->keterangan = '';
        $this->tanggal_pelunasan = now()->format('Y-m-d');
        $this->metode_pembayaran = PenjualanPelunasanService::METODE_TUNAI;
        $this->mode_ui = 'PELUNASAN';
    }

    public function batalPilihNota(): void
    {
        $this->penjualan_id = null;
        $this->selectedNota = null;
        $this->nominal = 0;
        $this->nominal_tunai = 0;
        $this->nominal_transfer = 0;
        $this->nominal_split_a = 0;
        $this->nominal_split_b = 0;
        $this->akun_pelunasan_id = null;
        $this->akun_split_a_id = null;
        $this->akun_split_b_id = null;
        $this->rekening_perusahaan_id = null;
        $this->keterangan = '';
        $this->tanggal_pelunasan = null;
        $this->metode_pembayaran = PenjualanPelunasanService::METODE_TUNAI;
        $this->mode_ui = 'PELUNASAN';
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

    /**
     * Simpan pelunasan. Menerjemahkan mode UI (PELUNASAN / SPLIT) ke
     * payload yang dimengerti PenjualanPelunasanService.
     *
     * PELUNASAN (1 akun):
     *   - 1101.1 / 1101.2 → METODE_TUNAI, nominal_tunai = nominal
     *   - 1101.3–.8       → METODE_TRANSFER, rekening_perusahaan_id dari rekening yang terhubung
     *
     * SPLIT (2 akun):
     *   - Selalu METODE_CAMPUR
     *   - Slot yang akunnya kas tunai → nominal_tunai
     *   - Slot yang akunnya bank     → nominal_transfer + rekening_perusahaan_id dari rekening terhubung
     */
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
            $payload = $this->buildPayload();

            $service->prosesPelunasan($this->selectedNota, $payload);

            Notification::make()
                ->title('Sukses')
                ->body('Pelunasan berhasil disimpan.')
                ->success()
                ->send();

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

    /** Bangun payload untuk PenjualanPelunasanService dari state UI. */
    private function buildPayload(): array
    {
        $base = [
            'keterangan'       => $this->keterangan,
            'tanggal_pelunasan' => $this->tanggal_pelunasan,
        ];

        if ($this->mode_ui === 'PELUNASAN') {
            $akunId = $this->akun_pelunasan_id;

            if ($this->isKasTunai($akunId)) {
                // Kas tunai
                return array_merge($base, [
                    'metode_pembayaran' => PenjualanPelunasanService::METODE_TUNAI,
                    'nominal'           => $this->nominal,
                    'nominal_tunai'     => $this->nominal,
                    'nominal_transfer'  => 0,
                    'rekening_perusahaan_id' => null,
                ]);
            } else {
                // Bank / transfer
                $rek = $this->getRekeningUntukAkun($akunId);

                return array_merge($base, [
                    'metode_pembayaran'      => PenjualanPelunasanService::METODE_TRANSFER,
                    'nominal'                => $this->nominal,
                    'nominal_tunai'          => 0,
                    'nominal_transfer'       => $this->nominal,
                    'rekening_perusahaan_id' => $rek?->id,
                ]);
            }
        }

        // ── SPLIT ────────────────────────────────────────────────────────────
        $isTunaiA = $this->isKasTunai($this->akun_split_a_id);
        $isTunaiB = $this->isKasTunai($this->akun_split_b_id);

        // Hitung nominal tunai dan transfer dari dua slot
        $nominalTunai    = 0;
        $nominalTransfer = 0;
        $rekId           = null;

        if ($isTunaiA) {
            $nominalTunai += $this->nominal_split_a;
        } else {
            $nominalTransfer += $this->nominal_split_a;
            $rekId = $this->getRekeningUntukAkun($this->akun_split_a_id)?->id;
        }

        if ($isTunaiB) {
            $nominalTunai += $this->nominal_split_b;
        } else {
            $nominalTransfer += $this->nominal_split_b;
            $rekId = $rekId ?? $this->getRekeningUntukAkun($this->akun_split_b_id)?->id;
        }

        return array_merge($base, [
            'metode_pembayaran'      => PenjualanPelunasanService::METODE_CAMPUR,
            'nominal_tunai'          => $nominalTunai,
            'nominal_transfer'       => $nominalTransfer,
            'rekening_perusahaan_id' => $rekId,
        ]);
    }

    protected function getViewData(): array
    {
        return [
            'notaResults'      => $this->notaResults,
            'akunKasList'      => $this->akunKasList,
            'rekeningPerSubAkun' => $this->rekeningPerSubAkun,
            'rekeningPerusahaan' => $this->rekeningPerusahaan,
            'selectedBank'     => $this->selectedBank,
        ];
    }
}
