<?php

namespace App\Filament\Pages;

use App\Exports\JurnalUmumExport;
use App\Models\Barang;
use App\Models\JurnalUmum as JurnalModel;
use App\Models\SubAnakAkun;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class JurnalUmum extends Page implements HasActions, HasForms
{
    use HasPageShield;
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.pages.jurnal-umum';

    protected static UnitEnum|string|null $navigationGroup = 'Jurnal & Akuntansi';

    protected static ?string $title = 'Jurnal Umum';

    public $tgl;

    public $jurnal;

    public $no_dokumen;

    public $no_akun;

    public $nama_akun;

    public $nama;

    public $keterangan;

    public $harga = '';

    public $total = '';

    public $banyak = '';

    public $mm = '';

    public $m3 = '';

    public $hit_kbk = '';

    public $map = 'd';

    public $items = [];

    // ── Pemilihan barang saat 1 akun dipakai oleh >1 barang ──────────
    public $id_barang = null;

    public array $barangOptions = [];

    public bool $showBarangPicker = false;

    public bool $wasBalanced = false;

    public $filterTglDariInput = '';

    public $filterTglSampaiInput = '';

    public $filterTglDari = '';

    public $filterTglSampai = '';

    public int $perPage = 50;

    public bool $hasMorePages = true;

    public bool $isLoadingMore = false;

    public array $selectedIds = [];

    public bool $selectAll = false;

    public function mount(): void
    {
        $draft = session()->get('jurnal_draft', []);

        $this->tgl = $draft['tgl'] ?? now()->format('Y-m-d');
        $this->items = $draft['items'] ?? [];
        $this->harga = $draft['harga'] ?? '';
        $this->no_dokumen = $draft['nodok'] ?? '';
        $this->nama = $draft['nama'] ?? '';
        $this->mm = $draft['mm'] ?? '';
        $this->m3 = $draft['m3'] ?? '';
        $this->hit_kbk = $draft['hitkbk'] ?? '';
        $this->jurnal = $draft['kode'] ?? $this->getNextJurnalNumber();
        $this->total = $draft['total'] ?? '';
        $this->banyak = $draft['banyak'] ?? (strtolower($this->hit_kbk) === 'b' ? 1 : '');

        $savedMap = $draft['map'] ?? 'd';
        $this->map = in_array(strtolower($savedMap), ['d', 'k']) ? strtolower($savedMap) : 'd';
    }

    protected function getNextJurnalNumber(): int
    {
        $last = JurnalModel::max('jurnal');

        return $last ? (int) $last + 1 : 1;
    }

    private function persistDraftState(): void
    {
        session()->put('jurnal_draft', [
            'items' => $this->items,
            'kode' => $this->jurnal,
            'tgl' => $this->tgl,
            'map' => $this->map,
            'harga' => $this->harga,
            'banyak' => $this->banyak,
            'nodok' => $this->no_dokumen,
            'nama' => $this->nama,
            'mm' => $this->mm,
            'm3' => $this->m3,
            'hitkbk' => $this->hit_kbk,
            'total' => $this->total,
        ]);
    }

    /**
     * ── FITUR EXPORT JURNAL UMUM ──────────────────────────────
     * Menampilkan tombol "Export Excel" di header halaman.
     * Saat diklik, muncul modal untuk memilih rentang tanggal,
     * lalu file .xlsx otomatis terdownload dengan format yang
     * sama persis seperti sheet "isi jurnal" pada file referensi.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportJurnal')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->modalHeading('Export Jurnal Umum')
                ->modalDescription('Pilih rentang tanggal yang ingin diexport. Kosongkan jika ingin export semua data.')
                ->modalSubmitActionLabel('Export')
                ->form([
                    Grid::make(2)->schema([
                        DatePicker::make('tgl_dari')
                            ->label('Dari Tanggal')
                            ->native(true),
                        DatePicker::make('tgl_sampai')
                            ->label('Sampai Tanggal')
                            ->native(true)
                            ->default(now()->format('Y-m-d')),
                    ]),
                ])
                ->action(function (array $data) {
                    $tglDari = $data['tgl_dari'] ?? null;
                    $tglSampai = $data['tgl_sampai'] ?? null;

                    $fileName = 'jurnal-umum';
                    if ($tglDari || $tglSampai) {
                        $fileName .= '_'.($tglDari ?: 'awal').'_sd_'.($tglSampai ?: 'akhir');
                    }
                    $fileName .= '.xlsx';

                    return Excel::download(
                        new JurnalUmumExport($tglDari, $tglSampai),
                        $fileName
                    );
                }),
        ];
    }

    protected function getViewData(): array
    {
        $query = JurnalModel::latest('id');
        if (! empty($this->filterTglDari)) {
            $query->whereDate('tgl', '>=', $this->filterTglDari);
        }
        if (! empty($this->filterTglSampai)) {
            $query->whereDate('tgl', '<=', $this->filterTglSampai);
        }

        // ── FIX: tambahkan selectRaw "total" agar kolom Debit/Kredit
        //         per baris di tabel history tidak menampilkan 0.
        //         Sebelumnya hanya select() kolom mentah tanpa "total",
        //         sehingga $hj->total selalu null di Blade.
        $data = $query->limit($this->perPage + 1)
            ->select([
                'id',
                'tgl',
                'jurnal',
                'no-dokumen',
                'no_akun',
                'nama_akun',
                'nama',
                'keterangan',
                'hit_kbk',
                'banyak',
                'm3',
                'harga',
                'map',
                'id_barang',
            ])
            ->selectRaw("
                CASE LOWER(hit_kbk)
                    WHEN 'b' THEN banyak * harga
                    WHEN 'm' THEN m3 * harga
                    ELSE harga
                END as total
            ")
            ->get();

        $this->hasMorePages = $data->count() > $this->perPage;
        $historyJurnals = $data->take($this->perPage);

        // ── Ambil nama barang untuk baris yang punya id_barang ──────
        $barangIds = $historyJurnals->pluck('id_barang')->filter()->unique()->values();
        if ($barangIds->isNotEmpty()) {
            $barangNames = Barang::whereIn('id', $barangIds)->pluck('nama_barang', 'id');
            $historyJurnals->each(function ($hj) use ($barangNames) {
                $hj->nama_barang = $hj->id_barang ? ($barangNames[$hj->id_barang] ?? null) : null;
            });
        } else {
            $historyJurnals->each(function ($hj) {
                $hj->nama_barang = null;
            });
        }

        $totalsQuery = JurnalModel::query();
        if (! empty($this->filterTglDari)) {
            $totalsQuery->whereDate('tgl', '>=', $this->filterTglDari);
        }
        if (! empty($this->filterTglSampai)) {
            $totalsQuery->whereDate('tgl', '<=', $this->filterTglSampai);
        }

        $totals = $totalsQuery->selectRaw("
                map,
                SUM(
                    CASE LOWER(hit_kbk)
                        WHEN 'b' THEN banyak * harga
                        WHEN 'm' THEN m3 * harga
                        ELSE harga
                    END
                ) as total
            ")
            ->groupBy('map')
            ->pluck('total', 'map');

        $totalDebitDB = (float) ($totals[strtolower('d')] ?? 0);
        $totalKreditDB = (float) ($totals[strtolower('k')] ?? 0);
        $isHistoryBalanced = abs($totalDebitDB - $totalKreditDB) < 0.01;
        $selisihDB = abs($totalDebitDB - $totalKreditDB);

        // OPTIMASI CACHE: Simpan dalam bentuk array agar tidak menyentuh limit 1MB DB Cache
        $accountsMap = cache()->remember('sub_anak_akun_map_v2', 600, function () {
            return SubAnakAkun::pluck('nama_sub_anak_akun', 'kode_sub_anak_akun')->toArray();
        });

        $accounts = collect($accountsMap)->map(fn ($nama, $no) => (object) ['no' => $no, 'nama' => $nama])->values();

        return [
            'accounts' => $accounts,
            'historyJurnals' => $historyJurnals,
            'totalDebitDB' => $totalDebitDB,
            'totalKreditDB' => $totalKreditDB,
            'isHistoryBalanced' => $isHistoryBalanced,
            'selisihDB' => $selisihDB,
        ];
    }

    public function applyFilter(): void
    {
        $this->filterTglDari = $this->filterTglDariInput;
        $this->filterTglSampai = $this->filterTglSampaiInput;
        $this->perPage = 50;
        $this->hasMorePages = true;
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function resetFilter(): void
    {
        $this->filterTglDariInput = '';
        $this->filterTglSampaiInput = '';
        $this->filterTglDari = '';
        $this->filterTglSampai = '';
        $this->perPage = 50;
        $this->hasMorePages = true;
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function loadMore(): void
    {
        if (! $this->hasMorePages) {
            return;
        }
        $this->perPage += 50;
        $this->selectAll = false;
    }

    protected function isDraftBalanced(): bool
    {
        if (empty($this->items)) {
            return false;
        }

        $data = collect($this->items);
        $debit = (float) $data->filter(fn ($i) => strtolower($i['map']) === 'd')->sum('total');
        $kredit = (float) $data->filter(fn ($i) => strtolower($i['map']) === 'k')->sum('total');

        return abs($debit - $kredit) < 0.01;
    }

    public function updatedNoAkun($value): void
    {
        $this->syncBarangPickerForAkun($value);
    }

    /**
     * Sinkronkan nama_akun + opsi barang untuk kode akun yang dipilih.
     * Dipanggil dari hook Livewire (updatedNoAkun) DAN secara eksplisit
     * lewat $wire.syncBarangPickerForAkun(...) di Alpine — supaya tidak
     * bergantung sepenuhnya pada timing hook otomatis yang kadang tidak
     * ter-trigger saat properti diubah langsung dari sisi Alpine.
     */
    public function syncBarangPickerForAkun($value): void
    {
        if (blank($value)) {
            $this->nama_akun = '';
            $this->id_barang = null;
            $this->barangOptions = [];
            $this->showBarangPicker = false;

            return;
        }

        $accountsMap = cache()->remember('sub_anak_akun_map_v2', 600, function () {
            return SubAnakAkun::pluck('nama_sub_anak_akun', 'kode_sub_anak_akun')->toArray();
        });

        $this->nama_akun = $accountsMap[$value] ?? '';

        $barangs = Barang::whereHas(
            'subAnakAkun',
            fn ($q) => $q->where('kode_sub_anak_akun', $value)
        )->get(['id', 'nama_barang']);

        if ($barangs->isNotEmpty()) {
            // Dinamis: selama akun ini punya barang terkait (1 atau lebih), tampilkan picker
            $this->id_barang = $barangs->count() === 1 ? $barangs->first()->id : null;
            $this->barangOptions = $barangs->map(fn ($b) => [
                'id' => $b->id,
                'nama' => $b->nama_barang,
            ])->values()->toArray();
            $this->showBarangPicker = true;
        } else {
            $this->id_barang = null;
            $this->barangOptions = [];
            $this->showBarangPicker = false;
        }
    }

    public function updatedHitKbk($value): void
    {
        if (blank($value)) {
            $this->total = $this->harga;
        }
    }

    private array $transientProps = [
        'no_akun',
        'id_barang',
        'nama_akun',
        'nama',
        'keterangan',
        'mm',
        'm3',
        'harga',
        'banyak',
        'hit_kbk',
        'map',
        'total',
        'no_dokumen',
    ];

    public function updated($propertyName): void
    {
        if (! in_array($propertyName, $this->transientProps)) {
            $this->persistDraftState();
        }
    }

    public function addItem($rawHarga = null, $rawBanyak = null, $rawM3 = null, $rawTotal = null): void
    {
        if ($rawHarga !== null) {
            $this->harga = $rawHarga;
        }
        if ($rawBanyak !== null) {
            $this->banyak = $rawBanyak;
        }
        if ($rawM3 !== null) {
            $this->m3 = $rawM3;
        }
        if ($rawTotal !== null) {
            $this->total = $rawTotal;
        }

        $errors = [];

        if (blank($this->no_akun)) {
            $errors[] = 'No. Akun wajib dipilih.';
        }
        if (blank($this->nama_akun)) {
            $errors[] = 'Nama Akun belum terisi.';
        }
        if ($this->showBarangPicker && ! empty($this->barangOptions) && blank($this->id_barang)) {
            $errors[] = 'Akun ini dipakai oleh beberapa barang, pilih barangnya terlebih dahulu.';
        }
        if (blank($this->harga) || (float) $this->harga < 0.01) {
            $errors[] = 'Harga wajib diisi (minimal Rp 1).';
        }

        if (! empty($errors)) {
            foreach ($errors as $err) {
                $this->dispatch('toast', type: 'error', title: 'Validasi Gagal', msg: $err);
            }

            return;
        }

        $harga = (float) $this->harga;
        $banyak = blank($this->banyak) ? null : (float) $this->banyak;
        $m3 = blank($this->m3) ? null : (float) str_replace(',', '.', $this->m3);

        $total = match ($this->hit_kbk) {
            'b' => ($banyak ?? 0.0) * $harga,
            'm' => ($m3 ?? 0.0) * $harga,
            default => $harga,
        };

        if ($total < 0.01) {
            $this->dispatch('toast', type: 'error', title: 'Validasi Gagal', msg: match ($this->hit_kbk) {
                'b' => 'Kuantitas harus lebih dari 0.',
                'm' => 'Kubikasi (M3) harus lebih dari 0.',
                default => 'Harga harus lebih dari 0.',
            });

            return;
        }

        // ── Resolve nama barang (hanya untuk ditampilkan di draft, TIDAK diinsert ke DB) ──
        $namaBarangTerpilih = null;
        if (! blank($this->id_barang)) {
            $namaBarangTerpilih = Barang::find($this->id_barang)?->nama_barang;
        }

        $this->items[] = [
            'tgl' => $this->tgl,
            'jurnal' => $this->jurnal,
            'no_dokumen' => $this->no_dokumen,
            'no_akun' => $this->no_akun,
            'id_barang' => $this->id_barang,
            'nama_barang' => $namaBarangTerpilih,
            'nama_akun' => $this->nama_akun,
            'nama' => $this->nama,
            'mm' => $this->mm === '' ? null : (int) $this->mm,
            'keterangan' => $this->keterangan,
            'hit_kbk' => $this->hit_kbk,
            'banyak' => $banyak,
            'm3' => $m3,
            'harga' => $harga,
            'total' => $total,
            'map' => strtolower($this->map),
        ];

        $this->reset([
            'no_akun',
            'nama_akun',
            'nama',
            'keterangan',
            'mm',
            'm3',
            'no_dokumen',
            'harga',
            'total',
            'hit_kbk',
            'id_barang',
            'barangOptions',
            'showBarangPicker',
        ]);
        $this->banyak = '';

        $this->dispatch('toast', type: 'info', title: 'Item Ditambahkan', msg: 'Item berhasil masuk ke draft.');
        $this->persistDraftState();
    }

    public function removeItem(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        array_splice($this->items, $index, 1);
        $this->dispatch('toast', type: 'error', title: 'Item Dihapus', msg: 'Item berhasil dihapus dari draft.');
        $this->persistDraftState();
    }

    public function saveJurnal(): void
    {
        if (empty($this->items) || ! $this->isDraftBalanced()) {
            return;
        }

        // ── Auto-resolve jika No. Jurnal ternyata sudah dipakai transaksi lain ──
        if (JurnalModel::where('jurnal', $this->jurnal)->exists()) {
            $this->jurnal = $this->getNextJurnalNumber();

            foreach ($this->items as &$item) {
                $item['jurnal'] = $this->jurnal;
            }
            unset($item);

            $this->persistDraftState();

            $this->dispatch(
                'toast',
                type: 'info',
                title: 'No. Jurnal Diperbarui',
                msg: "No. Jurnal sebelumnya sudah terpakai, otomatis diganti menjadi {$this->jurnal}."
            );
        }

        try {
            DB::transaction(function () {
                foreach ($this->items as $item) {
                    $insertData = $item;
                    unset($insertData['total']);
                    unset($insertData['nama_barang']);

                    if (array_key_exists('no_dokumen', $insertData)) {
                        $insertData['no-dokumen'] = $insertData['no_dokumen'];
                        unset($insertData['no_dokumen']);
                    }

                    JurnalModel::create([
                        ...$insertData,
                        'status' => 'belum sinkron',
                        'created_by' => Auth::user()->name,
                    ]);
                }
            });
        } catch (QueryException $e) {
            // Fallback kalau ternyata masih bentrok di level DB (unique constraint / race condition)
            if ((int) $e->getCode() === 23000) {
                $this->jurnal = $this->getNextJurnalNumber();
                foreach ($this->items as &$item) {
                    $item['jurnal'] = $this->jurnal;
                }
                unset($item);
                $this->persistDraftState();

                $this->dispatch('toast', type: 'error', title: 'Bentrok Nomor Jurnal', msg: "No. Jurnal bentrok saat disimpan, sudah diganti ke {$this->jurnal}. Silakan klik Posting Jurnal sekali lagi.");

                return;
            }

            $this->dispatch('toast', type: 'error', title: 'Error Sistem', msg: 'Gagal menyimpan jurnal: '.$e->getMessage());

            return;
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', title: 'Error Sistem', msg: 'Gagal menyimpan jurnal: '.$e->getMessage());

            return;
        }

        session()->forget('jurnal_draft');

        $this->items = [];
        $this->wasBalanced = false;
        $this->reset(['no_akun', 'nama_akun', 'nama', 'keterangan', 'mm', 'm3', 'id_barang', 'barangOptions', 'showBarangPicker']);
        $this->harga = '';
        $this->banyak = '';
        $this->map = 'd';
        $this->hit_kbk = '';
        $this->total = '';
        $this->perPage = 50;
        $this->hasMorePages = true;
        $this->jurnal = $this->getNextJurnalNumber();

        $this->dispatch('toast', type: 'success', title: 'Jurnal Diposting!', msg: 'Semua entri berhasil disimpan ke database.');
    }

    public function resetForm(): void
    {
        $this->reset(['no_akun', 'nama_akun', 'nama', 'keterangan', 'mm', 'm3', 'id_barang', 'barangOptions', 'showBarangPicker']);
        $this->harga = '';
        $this->map = 'd';
        $this->hit_kbk = '';
        $this->m3 = '';
        $this->total = '';
    }

    public function toggleSelectAll(array $ids): void
    {
        if ($this->selectAll) {
            $this->selectedIds = [];
            $this->selectAll = false;
        } else {
            $this->selectedIds = $ids;
            $this->selectAll = true;
        }
    }

    public function toggleSelected(int $id): void
    {
        if (in_array($id, $this->selectedIds)) {
            $this->selectedIds = array_values(array_filter(
                $this->selectedIds,
                fn ($i) => $i !== $id
            ));
        } else {
            $this->selectedIds[] = $id;
        }
        $this->selectAll = false;
    }

    public function bulkDelete(): void
    {
        if (empty($this->selectedIds)) {
            $this->dispatch('toast', type: 'error', title: 'Tidak ada yang dipilih', msg: 'Centang minimal 1 baris terlebih dahulu.');

            return;
        }

        $count = count($this->selectedIds);

        try {
            JurnalModel::whereIn('id', $this->selectedIds)->delete();
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', title: 'Gagal Hapus', msg: $e->getMessage());

            return;
        }

        $this->selectedIds = [];
        $this->selectAll = false;

        $this->dispatch('toast', type: 'success', title: 'Berhasil Dihapus', msg: "{$count} transaksi berhasil dihapus.");
        $this->dispatch('bulk-delete-done');
    }

    protected function getJurnalFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                DatePicker::make('tgl')->label('Tanggal')->required()->native(false),
                TextInput::make('jurnal')->label('No. Jurnal')->required(),
                TextInput::make('no_dokumen')->label('No. Dokumen'),
                TextInput::make('nama')->label('Nama'),

                Select::make('no_akun')
                    ->label('Cari Nomor Akun')
                    ->required()
                    ->searchable()
                    ->options(function () {
                        $accountsMap = cache()->remember('sub_anak_akun_map_v2', 600, function () {
                            return SubAnakAkun::pluck('nama_sub_anak_akun', 'kode_sub_anak_akun')->toArray();
                        });

                        return collect($accountsMap)->mapWithKeys(fn ($nama, $no) => [
                            $no => "{$no} - {$nama}",
                        ]);
                    })
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        $accountsMap = cache()->remember('sub_anak_akun_map_v2', 600, function () {
                            return SubAnakAkun::pluck('nama_sub_anak_akun', 'kode_sub_anak_akun')->toArray();
                        });
                        $set('nama_akun', $accountsMap[$state] ?? '');
                        $set('id_barang', null); // reset pilihan barang saat akun ganti
                    }),
                TextInput::make('nama_akun')->label('Nama Akun')->required()->readOnly(),

                // ── FIELD BARU: Nama Barang, muncul hanya jika akun punya barang terkait ──
                Select::make('id_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->options(function (Get $get) {
                        $noAkun = $get('no_akun');
                        if (blank($noAkun)) {
                            return [];
                        }

                        return Barang::whereHas(
                            'subAnakAkun',
                            fn ($q) => $q->where('kode_sub_anak_akun', $noAkun)
                        )->pluck('nama_barang', 'id');
                    })
                    ->visible(function (Get $get) {
                        $noAkun = $get('no_akun');
                        if (blank($noAkun)) {
                            return false;
                        }

                        // Dinamis: field muncul jika akun ini punya barang terkait, apapun kodenya
                        return Barang::whereHas(
                            'subAnakAkun',
                            fn ($q) => $q->where('kode_sub_anak_akun', $noAkun)
                        )->exists();
                    })
                    ->required(fn (Get $get) => Barang::whereHas(
                        'subAnakAkun',
                        fn ($q) => $q->where('kode_sub_anak_akun', $get('no_akun'))
                    )->exists())
                    ->live(),

                TextInput::make('mm')->label('MM (Tebal Plywood)')->numeric(),
                TextInput::make('keterangan')->label('Keterangan'),

                Select::make('hit_kbk')
                    ->label('Hit KBK')
                    ->options([
                        'b' => 'Banyak',
                        'm' => 'Kubikasi',
                    ])
                    ->placeholder('-- Tidak ada --'),
                Select::make('map')
                    ->label('Posisi')
                    ->options(['d' => 'Debit', 'k' => 'Kredit'])
                    ->required(),

                TextInput::make('banyak')->label('Kuantitas (Banyak)')->numeric(),
                TextInput::make('m3')->label('Kubikasi (M3)')->numeric(),

                TextInput::make('harga')
                    ->label('Harga Satuan')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->columnSpanFull(),
            ]),
        ];
    }

    public function editDraftAction(): Action
    {
        return Action::make('editDraft')
            ->modalHeading('Edit Draft Transaksi')
            ->modalSubmitActionLabel('Simpan Perubahan')
            ->form($this->getJurnalFormSchema())
            ->fillForm(function (array $arguments) {
                return $this->items[$arguments['index']];
            })
            ->action(function (array $data, array $arguments, Action $action): void {
                $index = $arguments['index'];

                $data['hit_kbk'] = $data['hit_kbk'] ?? '';
                $data['keterangan'] = $data['keterangan'] ?? '';
                $data['no_dokumen'] = $data['no_dokumen'] ?? '';
                $data['nama'] = $data['nama'] ?? '';

                $harga = blank($data['harga'] ?? null) ? 0.0 : (float) $data['harga'];
                $banyak = blank($data['banyak'] ?? null) ? null : (float) $data['banyak'];
                $m3 = blank($data['m3'] ?? null) ? null : (float) str_replace(',', '.', $data['m3']);
                $hit_kbk = $data['hit_kbk'];

                $total = match ($hit_kbk) {
                    'b' => ($banyak ?? 0.0) * $harga,
                    'm' => ($m3 ?? 0.0) * $harga,
                    default => $harga,
                };

                $errors = $this->validateJurnalData($hit_kbk, $harga, $total, $banyak, $m3);

                if (! empty($errors)) {
                    Notification::make()->title('Validasi Gagal')->body($errors[0])->danger()->send();
                    $action->halt();

                    return;
                }

                $data['banyak'] = $banyak;
                $data['m3'] = $m3;
                $data['harga'] = $harga;
                $data['total'] = $total;

                $this->items[$index] = array_merge($this->items[$index], $data);
                $this->persistDraftState();
                Notification::make()->title('Draft berhasil diperbarui')->success()->send();
            });
    }

    public function editHistoryAction(): Action
    {
        return Action::make('editHistory')
            ->visible(fn () => auth()->user()?->hasRole('super_admin'))
            ->modalHeading('Edit Transaksi Riwayat')
            ->modalSubmitActionLabel('Simpan Perubahan')
            ->form($this->getJurnalFormSchema())
            ->fillForm(function (array $arguments) {
                $record = JurnalModel::find($arguments['id']);
                if (! $record) {
                    return [];
                }

                $data = $record->toArray();
                $data['no_dokumen'] = $record->{'no-dokumen'} ?? $record->no_dokumen;
                $data['id_barang'] = $record->id_barang;

                return $data;
            })
            ->action(function (array $data, array $arguments, Action $action): void {
                $record = JurnalModel::find($arguments['id']);
                if (! $record) {
                    Notification::make()->title('Error')->body('Data tidak ditemukan.')->danger()->send();

                    return;
                }

                $data['hit_kbk'] = $data['hit_kbk'] ?? '';
                $data['keterangan'] = $data['keterangan'] ?? '';
                $data['no_dokumen'] = $data['no_dokumen'] ?? '';
                $data['nama'] = $data['nama'] ?? '';

                $harga = blank($data['harga'] ?? null) ? 0.0 : (float) $data['harga'];
                $banyak = blank($data['banyak'] ?? null) ? null : (float) $data['banyak'];
                $m3 = blank($data['m3'] ?? null) ? null : (float) str_replace(',', '.', $data['m3']);
                $hit_kbk = $data['hit_kbk'];

                $total = match ($hit_kbk) {
                    'b' => ($banyak ?? 0.0) * $harga,
                    'm' => ($m3 ?? 0.0) * $harga,
                    default => $harga,
                };

                $errors = $this->validateJurnalData($hit_kbk, $harga, $total, $banyak, $m3);

                if (! empty($errors)) {
                    Notification::make()->title('Validasi Gagal')->body($errors[0])->danger()->send();
                    $action->halt();

                    return;
                }

                $data['banyak'] = $banyak;
                $data['m3'] = $m3;
                $data['harga'] = $harga;

                $updateData = $data;
                unset($updateData['total']);

                if (array_key_exists('no_dokumen', $updateData)) {
                    $updateData['no-dokumen'] = $updateData['no_dokumen'];
                    unset($updateData['no_dokumen']);
                }

                $record->update($updateData);
                Notification::make()->title('Data riwayat berhasil diperbarui')->success()->send();
            });
    }

    public function deleteHistoryAction(): Action
    {
        return Action::make('deleteHistory')
            ->visible(fn () => auth()->user()?->hasRole('super_admin'))
            ->requiresConfirmation()
            ->modalHeading('Hapus Transaksi')
            ->modalDescription('Yakin ingin menghapus data ini secara permanen?')
            ->modalSubmitActionLabel('Ya, Hapus')
            ->color('danger')
            ->action(function (array $arguments): void {
                $record = JurnalModel::find($arguments['id']);
                if ($record) {
                    $record->delete();
                    Notification::make()->title('Data riwayat berhasil dihapus')->success()->send();
                }
            });
    }

    protected function validateJurnalData(string $hit_kbk, float &$harga, float &$total, ?float &$banyak, ?float &$m3): array
    {
        $errors = [];
        if (blank($hit_kbk)) {
            if ($harga < 0.01 && $total >= 0.01) {
                $harga = $total;
            } elseif ($total < 0.01 && $harga >= 0.01) {
                $total = $harga;
            }
            if ($banyak === '' || $banyak === null) {
                $banyak = null;
            }
            if ($m3 === '' || $m3 === null) {
                $m3 = null;
            }
            if ($harga < 0.01) {
                $errors[] = 'Harga atau Total wajib diisi (minimal Rp 1).';
            }
            if (abs($total - $harga) >= 0.01) {
                $errors[] = 'Data tidak sesuai: Karena Hit KBK kosong, Total harus sama dengan Harga (Rp '.number_format($harga, 0, ',', '.').'), sedangkan Total diisi Rp '.number_format($total, 0, ',', '.').'.';
            }
        } else {
            if ($harga < 0.01) {
                $errors[] = 'Harga wajib diisi (minimal Rp 1).';
            }

            if ($hit_kbk === 'b') {
                if ($banyak === null || $banyak < 0.0001) {
                    $errors[] = 'Kuantitas (Banyak) harus diisi dan lebih dari 0.';
                } else {
                    $expected = $banyak * $harga;
                    if (abs($total - $expected) >= 0.01) {
                        $errors[] = 'Data tidak sesuai: Kuantitas ('.$banyak.') x Harga (Rp '.number_format($harga, 0, ',', '.').') = Rp '.number_format($expected, 0, ',', '.').', sedangkan Total diisi Rp '.number_format($total, 0, ',', '.').'.';
                    }
                }
            } elseif ($hit_kbk === 'm') {
                if ($m3 === null || $m3 < 0.000001) {
                    $errors[] = 'Kubikasi (M3) harus diisi dan lebih dari 0.';
                } else {
                    $expected = $m3 * $harga;
                    if (abs($total - $expected) >= 0.01) {
                        $errors[] = 'Data tidak sesuai: Kubikasi ('.$m3.') x Harga (Rp '.number_format($harga, 0, ',', '.').') = Rp '.number_format($expected, 2, ',', '.').', sedangkan Total diisi Rp '.number_format($total, 0, ',', '.').'.';
                    }
                }
            }
        }

        return $errors;
    }
}
