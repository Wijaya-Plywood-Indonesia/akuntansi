<?php

namespace App\Filament\Pages;

use App\Models\Pembelian;
use App\Models\PembelianMetodePembayaran;
use App\Models\RekeningPerusahaan;
use App\Services\PembelianKedatanganService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Filament\Support\Enums\Width;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use RuntimeException;
use UnitEnum;

class KedatanganBarangPembelian extends Page
{
    use HasPageShield;

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected string $view = 'filament.pages.kedatangan-barang-pembelian';

    protected static ?string $navigationLabel = 'Kedatangan Barang';

    protected static ?string $title = 'Kedatangan Barang & Pelunasan Hutang';

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    public ?string $search = '';

    public ?int $pembelian_id = null;

    public ?Pembelian $selectedNota = null;

    // ── Form "Tambah DP" ─────────────────────────────────────────────────
    public string $dp_nominal = '';

    public string $dp_payment_method = PembelianMetodePembayaran::METODE_TUNAI;

    public ?int $dp_rekening_perusahaan_id = null;

    public ?string $dp_reference_number = null;

    public ?string $dp_catatan = null;

    // ── Form "Konfirmasi Barang Datang" (khusus sisa DP) ────────────────
    public string $sisa_nominal = '';

    public string $sisa_payment_method = PembelianMetodePembayaran::METODE_TUNAI;

    public ?int $sisa_rekening_perusahaan_id = null;

    public ?string $sisa_reference_number = null;

    // ── Form "Bayar Hutang" (khusus NORMAL) ─────────────────────────────
    public string $hutang_nominal = '';

    public string $hutang_payment_method = PembelianMetodePembayaran::METODE_TUNAI;

    public ?int $hutang_rekening_perusahaan_id = null;

    public ?string $hutang_reference_number = null;

    public ?string $hutang_catatan = null;

    /** @var Collection<int, RekeningPerusahaan> */
    public Collection $rekeningPerusahaan;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->rekeningPerusahaan = RekeningPerusahaan::all();
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    #[Computed]
    public function notaResults(): Collection
    {
        return app(PembelianKedatanganService::class)->getMenungguKedatangan($this->search, 20);
    }

    public function pilihNota(int $id): void
    {
        $nota = Pembelian::with('detailPembelians')->find($id);

        if (! $nota) {
            Notification::make()
                ->title('Error')
                ->body('Pembelian tidak ditemukan.')
                ->danger()
                ->send();

            return;
        }

        $service = app(PembelianKedatanganService::class);

        $bisaKonfirmasiBarang = $service->bisaDikonfirmasi($nota);
        $bisaBayarHutang = $service->bisaBayarHutang($nota);

        if (! $bisaKonfirmasiBarang && ! $bisaBayarHutang) {
            Notification::make()
                ->title('Tidak Bisa Diproses')
                ->body('Nota ini tidak menunggu kedatangan barang maupun pelunasan hutang, atau sudah selesai diproses sebelumnya.')
                ->warning()
                ->send();

            return;
        }

        $this->pembelian_id = $id;
        $this->selectedNota = $nota;
        $this->resetFormFields();

        // Prefill nominal sisa untuk DP supaya user tinggal cek "Bayar Pas"
        // (tapi tetap wajib pas menutup sisa, tidak bisa diubah jadi kurang).
        if ($nota->jenis_pembayaran === Pembelian::JENIS_DP) {
            $this->sisa_nominal = $nota->sisaTagihan() > 0 ? (string) (int) $nota->sisaTagihan() : '0';
        }
    }

    public function batalPilihNota(): void
    {
        $this->pembelian_id = null;
        $this->selectedNota = null;
        $this->resetFormFields();
    }

    private function resetFormFields(): void
    {
        $this->dp_nominal = '';
        $this->dp_payment_method = PembelianMetodePembayaran::METODE_TUNAI;
        $this->dp_rekening_perusahaan_id = null;
        $this->dp_reference_number = null;
        $this->dp_catatan = null;

        $this->sisa_nominal = '';
        $this->sisa_payment_method = PembelianMetodePembayaran::METODE_TUNAI;
        $this->sisa_rekening_perusahaan_id = null;
        $this->sisa_reference_number = null;

        $this->hutang_nominal = '';
        $this->hutang_payment_method = PembelianMetodePembayaran::METODE_TUNAI;
        $this->hutang_rekening_perusahaan_id = null;
        $this->hutang_reference_number = null;
        $this->hutang_catatan = null;
    }

    private function parseNumber(string $value): float
    {
        $clean = preg_replace('/[^0-9]/', '', $value) ?: '0';

        return (float) $clean;
    }

    /**
     * Aksi "Tambah DP" — boleh dipanggil berkali-kali, nominal bebas (tidak
     * wajib langsung lunas). Hanya muncul/berlaku untuk nota jenis DP.
     */
    public function tambahDp(): void
    {
        if (! $this->selectedNota) {
            Notification::make()
                ->title('Error')
                ->body('Silakan pilih nota pembelian terlebih dahulu.')
                ->danger()
                ->send();

            return;
        }

        $service = app(PembelianKedatanganService::class);

        try {
            $updated = $service->tambahDp($this->selectedNota, [
                'nominal'                => $this->parseNumber($this->dp_nominal),
                'payment_method'         => $this->dp_payment_method,
                'rekening_perusahaan_id' => $this->dp_rekening_perusahaan_id,
                'reference_number'       => $this->dp_reference_number,
                'catatan'                => $this->dp_catatan,
            ], auth()->id());

            Notification::make()
                ->title('DP Berhasil Ditambahkan')
                ->body('Jurnal Uang Muka Pembelian sudah tercatat. Sisa tagihan sekarang: Rp '.number_format($updated->sisaTagihan()).'.')
                ->success()
                ->send();

            // Refresh nota yang lagi dipilih (sisa tagihan & total dibayar
            // berubah) tanpa kehilangan panel yang lagi terbuka.
            $this->selectedNota = $updated->load('detailPembelians');
            $this->dp_nominal = '';
            $this->dp_reference_number = null;
            $this->dp_catatan = null;
            $this->sisa_nominal = $updated->sisaTagihan() > 0 ? (string) (int) $updated->sisaTagihan() : '0';
            unset($this->notaResults);
        } catch (InvalidArgumentException|RuntimeException $e) {
            Notification::make()
                ->title('Gagal Menambah DP')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Aksi "Bayar Hutang" — khusus nota jenis NORMAL yang masih ada sisa
     * hutang. Boleh dipanggil berkali-kali (cicilan), nominal bebas.
     */
    public function bayarHutang(): void
    {
        if (! $this->selectedNota) {
            Notification::make()
                ->title('Error')
                ->body('Silakan pilih nota pembelian terlebih dahulu.')
                ->danger()
                ->send();

            return;
        }

        $service = app(PembelianKedatanganService::class);

        try {
            $updated = $service->bayarHutang($this->selectedNota, [
                'nominal'                => $this->parseNumber($this->hutang_nominal),
                'payment_method'         => $this->hutang_payment_method,
                'rekening_perusahaan_id' => $this->hutang_rekening_perusahaan_id,
                'reference_number'       => $this->hutang_reference_number,
                'catatan'                => $this->hutang_catatan,
            ], auth()->id());

            Notification::make()
                ->title('Pembayaran Hutang Berhasil')
                ->body('Jurnal pelunasan sudah tercatat. Sisa hutang sekarang: Rp '.number_format($updated->sisaTagihan()).'.')
                ->success()
                ->send();

            unset($this->notaResults);

            if ($updated->sisaTagihan() <= 0) {
                // Sudah lunas total -> keluar dari panel, nota hilang dari daftar.
                $this->batalPilihNota();
            } else {
                $this->selectedNota = $updated->load('detailPembelians');
                $this->hutang_nominal = '';
                $this->hutang_reference_number = null;
                $this->hutang_catatan = null;
            }
        } catch (InvalidArgumentException|RuntimeException $e) {
            Notification::make()
                ->title('Gagal Membayar Hutang')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function konfirmasiDatang(): void
    {
        if (! $this->selectedNota) {
            Notification::make()
                ->title('Error')
                ->body('Silakan pilih nota pembelian terlebih dahulu.')
                ->danger()
                ->send();

            return;
        }

        $service = app(PembelianKedatanganService::class);

        $payload = [];

        if ($this->selectedNota->jenis_pembayaran === Pembelian::JENIS_DP) {
            $payload = [
                'nominal'                => $this->parseNumber($this->sisa_nominal),
                'payment_method'         => $this->sisa_payment_method,
                'rekening_perusahaan_id' => $this->sisa_rekening_perusahaan_id,
                'reference_number'       => $this->sisa_reference_number,
            ];
        }

        try {
            $service->konfirmasiBarangDatang($this->selectedNota, auth()->id(), $payload);

            Notification::make()
                ->title('Sukses')
                ->body('Barang dikonfirmasi datang. Persediaan & jurnal sudah tercatat.')
                ->success()
                ->send();

            $this->batalPilihNota();
            unset($this->notaResults);
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
        ];
    }
}