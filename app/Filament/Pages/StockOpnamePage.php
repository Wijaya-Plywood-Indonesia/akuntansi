<?php

namespace App\Filament\Pages;

use App\Models\Barang;
use App\Models\JurnalUmum;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Services\StockOpnameService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class StockOpnamePage extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    public static ?string $navigationLabel = 'Stock Opname';
    protected static string|UnitEnum|null $navigationGroup = 'Stock Barang';
    protected static ?int $navigationSort = 10;

    public function getView(): string
    {
        return 'filament.pages.stock-opname';
    }

    /* =========================
     |  STATE
     ========================= */

    public ?string $tanggal_opname = null;
    public ?string $catatan = null;
    public ?StockOpname $opname = null;
    public array $details = [];
    public ?string $catatan_approval = null;

    /** Daftar opname yang sedang berjalan (draft / menunggu / ditolak) */
    public array $daftarOpname = [];

    /** Riwayat opname yang sudah selesai */
    public array $riwayatOpname = [];

    // ── Filter riwayat ──────────────────────────────────────────────
    public ?string $filterTanggalDari = null;
    public ?string $filterTanggalSampai = null;
    public ?string $filterStatus = null;  // null = semua, atau salah satu status

    /* =========================
     |  MOUNT
     ========================= */

    public function mount(): void
    {
        // Default filter: bulan berjalan
        $this->filterTanggalDari = now()->startOfMonth()->format('Y-m-d');
        $this->filterTanggalSampai = now()->endOfMonth()->format('Y-m-d');

        $this->form->fill();
        $this->refreshDaftarOpname();
        $this->refreshRiwayatOpname();
    }

    /* =========================
     |  FORM
     ========================= */

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('tanggal_opname')
                ->label('Tanggal Opname')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->format('Y-m-d')
                ->maxDate(now())
                ->default(now())
                ->closeOnDateSelection()
                ->suffixIcon('heroicon-o-calendar')
                ->suffixIconColor('primary'),

            Textarea::make('catatan')
                ->label('Catatan Opname')
                ->placeholder('Opsional')
                ->rows(2),
        ]);
    }

    /* =========================
     |  REFRESH RIWAYAT OPNAME
     ========================= */

    public function refreshRiwayatOpname(): void
    {
        $query = StockOpname::with(['createdBy', 'approvedBy'])
            ->where('status', 'disetujui');

        if ($this->filterTanggalDari) {
            $query->whereDate('tanggal_opname', '>=', $this->filterTanggalDari);
        }
        if ($this->filterTanggalSampai) {
            $query->whereDate('tanggal_opname', '<=', $this->filterTanggalSampai);
        }

        $rows = $query->latest('approved_at')->limit(50)->get();

        $this->riwayatOpname = $rows->map(fn($o) => [
            'id' => $o->id,
            'no_opname' => $o->no_opname,
            'tanggal' => $o->tanggal_opname->format('d-m-Y'),
            'approved_by' => $o->approvedBy->name ?? '-',
            'approved_at' => $o->approved_at?->format('d-m-Y H:i') ?? '-',
            'created_by' => $o->createdBy->name ?? '-',
        ])->toArray();
    }

    public function refreshDaftarOpname(): void
    {
        $query = StockOpname::with(['createdBy'])
            ->whereIn('status', ['draft', 'menunggu', 'ditolak']);

        if ($this->filterStatus && in_array($this->filterStatus, ['draft', 'menunggu', 'ditolak'])) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterTanggalDari) {
            $query->whereDate('tanggal_opname', '>=', $this->filterTanggalDari);
        }
        if ($this->filterTanggalSampai) {
            $query->whereDate('tanggal_opname', '<=', $this->filterTanggalSampai);
        }

        $rows = $query->latest()->get();

        $this->daftarOpname = $rows->map(fn($o) => [
            'id' => $o->id,
            'no_opname' => $o->no_opname,
            'tanggal' => $o->tanggal_opname->format('d-m-Y'),
            'status' => $o->status,
            'created_by' => $o->createdBy->name ?? '-',
            'catatan' => $o->catatan ?? '',
        ])->toArray();
    }

    public function terapkanFilter(): void
    {
        $this->refreshDaftarOpname();
        $this->refreshRiwayatOpname();
    }

    public function resetFilter(): void
    {
        $this->filterTanggalDari = now()->startOfMonth()->format('Y-m-d');
        $this->filterTanggalSampai = now()->endOfMonth()->format('Y-m-d');
        $this->filterStatus = null;

        $this->refreshDaftarOpname();
        $this->refreshRiwayatOpname();
    }

    /* =========================
     |  BUKA OPNAME YANG ADA
     ========================= */

    public function bukaOpname(int $id): void
    {
        $found = StockOpname::find($id);

        if (!$found) {
            Notification::make()->title('Opname tidak ditemukan')->danger()->send();
            return;
        }

        $this->opname = $found;
        $this->loadDetails();
    }

    /* =========================
     |  HITUNG STOK BUKU BESAR (BATCH)
     ========================= */

    /**
     * Menghitung stok buku besar untuk sekumpulan barang sekaligus dalam
     * SATU query agregat ke JurnalUmum (meniru logika StokMatrix::mount()
     * di web telur), alih-alih memanggil accessor per-barang di dalam loop
     * (yang menyebabkan N+1 query dan halaman terasa berat).
     *
     * @param  \Illuminate\Support\Collection|iterable  $barangs  Koleksi Barang, WAJIB sudah eager-load relasi subAnakAkun
     * @return array<int, float>  key = barang_id, value = stok
     */
    protected function hitungStokBukuBesarBatch(iterable $barangs): array
    {
        $barangs = collect($barangs);

        $kodeAkuns = $barangs->map(fn($barang) => $barang->subAnakAkun?->kode_sub_anak_akun)
            ->filter()
            ->unique()
            ->toArray();

        $transaksisGrouped = JurnalUmum::select('no_akun', 'map', DB::raw('SUM(COALESCE(banyak, 0)) as total_qty'))
            ->whereIn('no_akun', $kodeAkuns)
            ->groupBy('no_akun', 'map')
            ->get()
            ->groupBy('no_akun');

        $hasil = [];

        foreach ($barangs as $barang) {
            $kodeAkun = $barang->subAnakAkun?->kode_sub_anak_akun;

            $totalQty = 0.0;
            if ($kodeAkun && isset($transaksisGrouped[$kodeAkun])) {
                foreach ($transaksisGrouped[$kodeAkun] as $trx) {
                    $isDebit = in_array(strtolower($trx->map), ['d', 'debit']);
                    $qty = (float) $trx->total_qty;

                    $totalQty += $isDebit ? $qty : -$qty;
                }
            }

            $hasil[$barang->id] = $totalQty;
        }

        return $hasil;
    }

    /* =========================
     |  MULAI OPNAME
     ========================= */

    public function mulaiOpname(): void
    {
        $state = $this->form->getState();
        $tanggalOpname = $state['tanggal_opname'] ?? today()->format('Y-m-d');

        // Cek apakah sudah ada opname draft/menunggu di tanggal yang sama
        $existing = StockOpname::whereDate('tanggal_opname', $tanggalOpname)
            ->whereIn('status', ['draft', 'menunggu'])
            ->latest()
            ->first();

        if ($existing) {
            $this->opname = $existing;
        } else {
            $this->opname = StockOpname::create([
                'tanggal_opname' => $tanggalOpname,
                'catatan' => $state['catatan'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            $barangs = Barang::with(['subAnakAkun', 'satuan'])
                ->whereHas('subAnakAkun', function ($query) {
                    $query->whereNotNull('kode_sub_anak_akun')
                        ->where('kode_sub_anak_akun', '!=', '');
                })
                ->orderBy('nama_barang')
                ->get();

            // Hitung stok buku besar untuk semua barang sekaligus (1 query), bukan per-barang
            $stokBukuBesarMap = $this->hitungStokBukuBesarBatch($barangs);

            foreach ($barangs as $barang) {
                $qtyJurnal = $stokBukuBesarMap[$barang->id] ?? 0.0;

                StockOpnameDetail::create([
                    'stock_opname_id' => $this->opname->id,
                    'barang_id' => $barang->id,
                    'stok_sistem' => $qtyJurnal,
                    'stok_aktual' => null,
                    'selisih' => null,
                ]);
            }
        }

        $this->loadDetails();
    }

    /* =========================
     |  LOAD DETAIL
     ========================= */

    public function loadDetails(): void
    {
        if (!$this->opname)
            return;

        // 💡 JIKA MASIH DRAFT: Sinkronkan barang-barang baru yang baru dihubungkan ke Jurnal secara dinamis
        if ($this->opname->isDraft()) {
            DB::transaction(function () {
                $barangs = Barang::with('subAnakAkun')
                    ->whereHas('subAnakAkun', function ($query) {
                        $query->whereNotNull('kode_sub_anak_akun')
                            ->where('kode_sub_anak_akun', '!=', '');
                    })
                    ->get();

                $existingBarangIds = $this->opname->details()->pluck('barang_id')->toArray();

                $barangBaru = $barangs->reject(fn($barang) => in_array($barang->id, $existingBarangIds));

                if ($barangBaru->isNotEmpty()) {
                    $stokBukuBesarMap = $this->hitungStokBukuBesarBatch($barangBaru);

                    foreach ($barangBaru as $barang) {
                        $qtyJurnal = $stokBukuBesarMap[$barang->id] ?? 0.0;

                        StockOpnameDetail::create([
                            'stock_opname_id' => $this->opname->id,
                            'barang_id' => $barang->id,
                            'stok_sistem' => $qtyJurnal,
                            'stok_aktual' => null,
                            'selisih' => null,
                        ]);
                    }
                }
            });
        }

        // Muat ulang detail relasi barang terbaru dari database
        $this->opname->load(['details.barang.subAnakAkun']);

        // Jika masih draft, hitung stok buku besar terbaru untuk SEMUA barang di detail sekaligus (1 query)
        $stokBukuBesarMap = $this->opname->isDraft()
            ? $this->hitungStokBukuBesarBatch($this->opname->details->pluck('barang')->filter())
            : [];

        $this->details = $this->opname->details
            ->filter(function ($d) {
                return $d->barang && $d->barang->subAnakAkun && !empty($d->barang->subAnakAkun->kode_sub_anak_akun);
            })
            ->map(function ($d) use ($stokBukuBesarMap) {
                $stokSistem = $this->opname->isDraft()
                    ? ($stokBukuBesarMap[$d->barang_id] ?? 0.0)
                    : (float) $d->stok_sistem;

                return [
                    'id' => $d->id,
                    'barang_id' => $d->barang_id,
                    'barang' => $d->barang->nama_barang ?? '-',
                    'kode' => $d->barang->kode_barang ?? '-',
                    'stok_sistem' => $stokSistem,
                    'stok_aktual' => $d->stok_aktual !== null ? (string) $d->stok_aktual : '',
                    'catatan' => $d->catatan ?? '',
                ];
            })
            ->values()
            ->toArray();
    }

    /* =========================
     |  SIMPAN PROGRESS
     ========================= */

    public function simpanProgress(): void
    {
        if (!$this->opname || !$this->opname->isDraft())
            return;

        DB::transaction(function () {
            foreach ($this->details as $item) {
                StockOpnameDetail::where('id', $item['id'])->update([
                    'stok_sistem' => (float) $item['stok_sistem'],
                    'stok_aktual' => $item['stok_aktual'] !== '' ? (float) $item['stok_aktual'] : null,
                    'catatan' => $item['catatan'] ?: null,
                ]);
            }
        });

        Notification::make()->title('Progress tersimpan')->success()->send();
    }

    /* =========================
     |  SUBMIT APPROVAL
     ========================= */

    public function submitApproval(): void
    {
        if (!$this->opname)
            return;

        $this->simpanProgress();
        $this->opname->refresh()->load('details');

        try {
            app(StockOpnameService::class)->submitUntukApproval($this->opname, auth()->id());

            Notification::make()
                ->title('Opname berhasil disubmit, menunggu approval')
                ->success()
                ->send();

            $this->loadDetails();
            $this->refreshDaftarOpname();
            $this->refreshRiwayatOpname();
        } catch (\Exception $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /* =========================
     |  APPROVE & POST TO JURNAL PEMBANTU
     ========================= */

    public function approve(): void
    {
        if (!$this->opname) {
            return;
        }

        try {
            DB::transaction(function () {
                app(StockOpnameService::class)->approve(
                    $this->opname,
                    auth()->id(),
                    $this->catatan_approval ?: null
                );

                $this->opname->load('details.barang.subAnakAkun');

                $nextJurnalNo = (int) (\App\Models\JurnalUmum::max('jurnal') ?? 0) + 1;
                $maxJP = (int) (\App\Models\JurnalPembantuHeader::max('jurnal') ?? 0);
                $nextJurnalNo = max($nextJurnalNo, $maxJP) + 1;

                // Hitung stok buku besar untuk SEMUA barang di opname ini sekaligus (1 query)
                $stokBukuBesarMap = $this->hitungStokBukuBesarBatch($this->opname->details->pluck('barang')->filter());

                foreach ($this->opname->details as $detail) {
                    $barang = $detail->barang;
                    $subAkun = $barang?->subAnakAkun;
                    $kodeAkun = $subAkun?->kode_sub_anak_akun;
                    $namaAkun = $subAkun?->nama_sub_anak_akun;

                    if (!$kodeAkun) {
                        continue;
                    }

                    $stokBukuBesarTerkini = $stokBukuBesarMap[$barang->id] ?? 0.0;
                    $stokAktualFisik = (float) ($detail->stok_aktual ?? 0);
                    $selisihOpname = $stokAktualFisik - $stokBukuBesarTerkini;

                    if ($selisihOpname == 0) {
                        continue;
                    }

                    $mapHeaderType = $selisihOpname > 0 ? 'd' : 'k';
                    $mapItemType = $selisihOpname > 0 ? 'debit' : 'kredit';
                    $qtyPenyesuaian = abs($selisihOpname);

                    $nextNoJurnalPembantu = (int) (\App\Models\JurnalPembantuHeader::max('no_jurnal_pembantu') ?? 0) + 1;

                    $jurnalPembantuHeader = \App\Models\JurnalPembantuHeader::create([
                        'no_jurnal_pembantu'  => $nextNoJurnalPembantu,
                        'tgl_transaksi'       => now()->format('Y-m-d'),
                        'jenis_transaksi'     => 'so',
                        'modul_asal'          => 'stock_opname',
                        'jurnal'              => $nextJurnalNo,
                        'no_akun'             => $kodeAkun,
                        'nama_akun'           => $namaAkun,
                        'map'                 => $mapHeaderType,
                        'no_dokumen'          => $this->opname->no_opname ?? 'OPNAME_STOK',
                        'keterangan'          => 'Opname Penyesuaian Fisik: ' . $barang->nama_barang . ' (Selisih: ' . ($selisihOpname > 0 ? '+' : '') . $selisihOpname . ')',
                        'total_nilai'         => 0.0,
                        'status'              => \App\Models\JurnalPembantuHeader::STATUS_DRAFT,
                        'adalah_jurnal_balik' => false,
                        'dibuat_oleh'         => auth()->id(),
                    ]);

                    $jurnalPembantuHeader->items()->create([
                        'urut'         => 1,
                        'barang_id'    => $barang->id,
                        'no_akun'      => $kodeAkun,
                        'nama_akun'    => $namaAkun,
                        'map'          => $mapItemType,
                        'nama_barang'  => $barang->nama_barang,
                        'no_dokumen'   => $this->opname->no_opname ?? 'OPNAME_STOK',
                        'banyak'       => $qtyPenyesuaian,
                        'harga'        => 0.0,
                        'jumlah'       => 0.0,
                        'status'       => true,
                        'keterangan'   => 'Opname Penyesuaian Fisik: ' . $barang->nama_barang . ' (Selisih: ' . ($selisihOpname > 0 ? '+' : '') . $selisihOpname . ')',
                        'created_by'   => auth()->id(),
                    ]);
                }
            });

            Notification::make()
                ->title('Opname disetujui, Jurnal Pembantu & Stok Baru sukses tercatat')
                ->success()
                ->send();

            $this->reset(['opname', 'details', 'catatan_approval', 'tanggal_opname', 'catatan']);
            $this->form->fill();
            $this->refreshDaftarOpname();
            $this->refreshRiwayatOpname();
        } catch (\Exception $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /* =========================
     |  TOLAK
     ========================= */

    public function tolak(): void
    {
        if (!$this->opname)
            return;

        try {
            app(StockOpnameService::class)->tolak(
                $this->opname,
                auth()->id(),
                $this->catatan_approval ?: null
            );

            Notification::make()->title('Opname ditolak')->warning()->send();

            $this->reset(['opname', 'details', 'catatan_approval', 'tanggal_opname', 'catatan']);
            $this->form->fill();
            $this->refreshDaftarOpname();
            $this->refreshRiwayatOpname();
        } catch (\Exception $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /* =========================
     |  BATAL
     ========================= */

    public function batal(): void
    {
        $this->reset(['opname', 'details', 'catatan_approval', 'tanggal_opname', 'catatan']);
        $this->form->fill();
        $this->refreshDaftarOpname();
        $this->refreshRiwayatOpname();
    }

    /* =========================
     |  PERMISSION
     ========================= */

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'manager']) ?? false;
    }
}