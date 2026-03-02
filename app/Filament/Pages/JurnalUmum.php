<?php

namespace App\Filament\Pages;

use App\Models\AnakAkun;
use App\Models\SubAnakAkun;
use App\Models\JurnalUmum as JurnalModel;
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
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class JurnalUmum extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.pages.jurnal-umum';
    protected static UnitEnum|string|null $navigationGroup = 'jurnal';
    protected static ?string $title = 'Jurnal Umum';

    public $tgl, $jurnal, $no_akun, $nama_akun, $keterangan, $harga, $banyak = 1, $map = 'D';
    public $items = [];

    public bool $wasBalanced = false;

    // ─────────────────────────────────────────────────────────────
    // FILTER & PAGINATION PROPERTIES
    // ─────────────────────────────────────────────────────────────

    /** Filter tanggal dari (input sementara, belum diapply) */
    public $filterTglDariInput  = '';

    /** Filter tanggal sampai (input sementara, belum diapply) */
    public $filterTglSampaiInput = '';

    /** Filter yang sudah diapply — ini yang dipakai query */
    public $filterTglDari  = '';
    public $filterTglSampai = '';

    /** Jumlah baris yang ditampilkan saat ini (bertambah tiap load more) */
    public int $perPage = 50;

    /** Apakah masih ada data yang bisa diload */
    public bool $hasMorePages = true;

    /** Apakah sedang loading (untuk animasi) */
    public bool $isLoadingMore = false;

    // ─────────────────────────────────────────────────────────────
    // MOUNT
    // ─────────────────────────────────────────────────────────────
    public function mount()
    {
        $this->tgl    = session()->get('jurnal_draft_tgl', now()->format('Y-m-d'));
        $this->items  = session()->get('jurnal_draft_items', []);
        $this->map    = session()->get('jurnal_draft_map', 'D');
        $this->banyak = session()->get('jurnal_draft_banyak', 1);
        $this->harga  = session()->get('jurnal_draft_harga', '');

        if (session()->has('jurnal_draft_kode')) {
            $this->jurnal = session()->get('jurnal_draft_kode');
        } else {
            $this->generateKodeJurnal();
        }

        $this->updateAutoBalanceSuggestion();
    }

    protected function generateKodeJurnal()
    {
        $last         = JurnalModel::max('jurnal');
        $this->jurnal = $last ? $last + 1 : 1;
        session()->put('jurnal_draft_kode', $this->jurnal);
    }

    private function persistDraftState(): void
    {
        session()->put('jurnal_draft_items',  $this->items);
        session()->put('jurnal_draft_kode',   $this->jurnal);
        session()->put('jurnal_draft_tgl',    $this->tgl);
        session()->put('jurnal_draft_map',    $this->map);
        session()->put('jurnal_draft_harga',  $this->harga);
        session()->put('jurnal_draft_banyak', $this->banyak);
    }

    // ─────────────────────────────────────────────────────────────
    // VIEW DATA — query dengan filter & limit
    // ─────────────────────────────────────────────────────────────
    protected function getViewData(): array
    {
        $sub  = SubAnakAkun::selectRaw("kode_sub_anak_akun as no, nama_sub_anak_akun as nama");
        $anak = AnakAkun::selectRaw("kode_anak_akun as no, nama_anak_akun as nama");

        // Query history dengan filter tanggal (hanya jika filter sudah diapply)
        $query = JurnalModel::latest('id');

        if (!empty($this->filterTglDari)) {
            $query->whereDate('tgl', '>=', $this->filterTglDari);
        }

        if (!empty($this->filterTglSampai)) {
            $query->whereDate('tgl', '<=', $this->filterTglSampai);
        }

        // Ambil perPage + 1 untuk deteksi apakah masih ada data berikutnya
        $data = $query->limit($this->perPage + 1)->get();

        // Cek apakah masih ada halaman berikutnya
        $this->hasMorePages = $data->count() > $this->perPage;

        // Potong ke perPage saja untuk ditampilkan
        $historyJurnals = $data->take($this->perPage);

        // ── Total dari SEMUA data sesuai filter (bukan hanya perPage) ──
        // Query ulang tanpa limit untuk akurasi balance check akuntan
        $totalsQuery = JurnalModel::query();
        if (!empty($this->filterTglDari)) {
            $totalsQuery->whereDate('tgl', '>=', $this->filterTglDari);
        }
        if (!empty($this->filterTglSampai)) {
            $totalsQuery->whereDate('tgl', '<=', $this->filterTglSampai);
        }
        $allForTotals = $totalsQuery->get(['map', 'banyak', 'harga']);

        $totalDebitDB  = $allForTotals->whereIn('map', ['D', 'debit', 'd', 'Debit'])->sum(fn($j) => $j->banyak * $j->harga);
        $totalKreditDB = $allForTotals->whereIn('map', ['K', 'kredit', 'k', 'Kredit'])->sum(fn($j) => $j->banyak * $j->harga);
        $isHistoryBalanced = abs($totalDebitDB - $totalKreditDB) < 0.01;
        $selisihDB = abs($totalDebitDB - $totalKreditDB);

        return [
            'accounts'          => $sub->unionAll($anak)->get(),
            'historyJurnals'    => $historyJurnals,
            'totalDebitDB'      => $totalDebitDB,
            'totalKreditDB'     => $totalKreditDB,
            'isHistoryBalanced' => $isHistoryBalanced,
            'selisihDB'         => $selisihDB,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // APPLY FILTER — dipanggil saat tombol Apply diklik
    // Reset perPage ke 50 agar mulai dari awal
    // ─────────────────────────────────────────────────────────────
    public function applyFilter(): void
    {
        $this->filterTglDari   = $this->filterTglDariInput;
        $this->filterTglSampai = $this->filterTglSampaiInput;
        $this->perPage         = 50;
        $this->hasMorePages    = true;
    }

    // ─────────────────────────────────────────────────────────────
    // RESET FILTER
    // ─────────────────────────────────────────────────────────────
    public function resetFilter(): void
    {
        $this->filterTglDariInput   = '';
        $this->filterTglSampaiInput = '';
        $this->filterTglDari        = '';
        $this->filterTglSampai      = '';
        $this->perPage              = 50;
        $this->hasMorePages         = true;
    }

    // ─────────────────────────────────────────────────────────────
    // LOAD MORE — infinite scroll, tambah 50 baris
    // ─────────────────────────────────────────────────────────────
    public function loadMore(): void
    {
        if (!$this->hasMorePages) return;

        $this->perPage += 50;
    }

    // ─────────────────────────────────────────────────────────────
    // SEMUA METHOD LAMA TIDAK BERUBAH
    // ─────────────────────────────────────────────────────────────

    protected function isDraftBalanced(): bool
    {
        if (empty($this->items)) return false;

        $data        = collect($this->items);
        $totalDebit  = (float) $data->whereIn('map', ['D', 'debit', 'd'])->sum('total');
        $totalKredit = (float) $data->whereIn('map', ['K', 'kredit', 'k'])->sum('total');

        return abs($totalDebit - $totalKredit) < 0.01;
    }

    private function updateAutoBalanceSuggestion()
    {
        if (empty($this->items)) {
            return;
        }

        $data        = collect($this->items);
        $totalDebit  = (float) $data->whereIn('map', ['D', 'debit', 'd'])->sum('total');
        $totalKredit = (float) $data->whereIn('map', ['K', 'kredit', 'k'])->sum('total');
        $selisih     = $totalDebit - $totalKredit;

        if (abs($selisih) > 0.01) {
            $this->harga  = abs($selisih);
            $this->map    = ($selisih > 0) ? 'K' : 'D';
            $this->banyak = 1;
        } else {
            $this->harga  = '';
            $this->banyak = 1;
        }
    }

    public function updatedNoAkun($value)
    {
        if (blank($value)) {
            $this->nama_akun = '';
            $this->harga     = '';
            $this->persistDraftState();
            return;
        }

        $this->nama_akun = SubAnakAkun::where('kode_sub_anak_akun', $value)->first()?->nama_sub_anak_akun
            ?? AnakAkun::where('kode_anak_akun', $value)->first()?->nama_anak_akun
            ?? '';

        $this->updateAutoBalanceSuggestion();
        $this->persistDraftState();
    }

    public function addItem()
    {
        $this->validate([
            'no_akun' => 'required',
            'harga'   => 'required|numeric|min:0.01',
        ]);

        $this->items[] = [
            'tgl'        => $this->tgl,
            'jurnal'     => $this->jurnal,
            'no_akun'    => $this->no_akun,
            'nama_akun'  => $this->nama_akun,
            'keterangan' => $this->keterangan,
            'banyak'     => (float) $this->banyak,
            'harga'      => (float) $this->harga,
            'total'      => (float) $this->banyak * (float) $this->harga,
            'map'        => $this->map,
        ];

        if ($this->isDraftBalanced()) {
            $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga']);
            $this->banyak      = 1;
            $this->map         = 'D';
            $this->wasBalanced = true;
            $this->jurnal      = (int) $this->jurnal + 1;
        } else {
            $this->reset(['no_akun', 'nama_akun', 'keterangan']);
            $this->harga  = '';
            $this->banyak = 1;
            $this->map    = 'D';
            $this->updateAutoBalanceSuggestion();
        }

        $this->persistDraftState();
    }

    public function removeItem(int $index)
    {
        if (isset($this->items[$index])) {
            array_splice($this->items, $index, 1);
            $this->updateAutoBalanceSuggestion();
            $this->persistDraftState();
        }
    }

    public function saveJurnal()
    {
        if (empty($this->items) || !$this->isDraftBalanced()) return;

        DB::transaction(function () {
            foreach ($this->items as $item) {
                JurnalModel::create([
                    ...$item,
                    'status'     => 'belum sinkron',
                    'created_by' => Auth::user()->name,
                ]);
            }
        });

        session()->forget([
            'jurnal_draft_items',
            'jurnal_draft_kode',
            'jurnal_draft_tgl',
            'jurnal_draft_map',
            'jurnal_draft_harga',
            'jurnal_draft_banyak',
        ]);

        $this->items       = [];
        $this->wasBalanced = false;
        $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga']);
        $this->banyak = 1;
        $this->map    = 'D';

        // Reset pagination setelah posting agar data baru langsung muncul
        $this->perPage      = 50;
        $this->hasMorePages = true;

        $this->generateKodeJurnal();

        Notification::make()->title('Jurnal Berhasil Diposting')->success()->send();
    }

    public function resetForm()
    {
        $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga']);
        $this->banyak = 1;
        $this->map    = 'D';
        $this->updateAutoBalanceSuggestion();
        $this->persistDraftState();
    }

    public function editHistoryAction(): Action
    {
        return Action::make('editHistory')
            ->modalHeading('Edit Transaksi Riwayat')
            ->modalSubmitActionLabel('Simpan Perubahan')
            ->form([
                Grid::make(2)->schema([
                    DatePicker::make('tgl')
                        ->label('Tanggal')
                        ->required()
                        ->native(false),

                    TextInput::make('jurnal')
                        ->label('No. Jurnal')
                        ->required(),

                    Select::make('no_akun')
                        ->label('Cari Nomor Akun')
                        ->required()
                        ->searchable()
                        ->options(function () {
                            $sub = SubAnakAkun::all()->mapWithKeys(fn($item) => [
                                $item->kode_sub_anak_akun => "{$item->kode_sub_anak_akun} - {$item->nama_sub_anak_akun}"
                            ]);
                            $anak = AnakAkun::all()->mapWithKeys(fn($item) => [
                                $item->kode_anak_akun => "{$item->kode_anak_akun} - {$item->nama_anak_akun}"
                            ]);
                            return $sub->merge($anak);
                        })
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            $name = SubAnakAkun::where('kode_sub_anak_akun', $state)->first()?->nama_sub_anak_akun
                                ?? AnakAkun::where('kode_anak_akun', $state)->first()?->nama_anak_akun
                                ?? '';
                            $set('nama_akun', $name);
                        }),

                    TextInput::make('nama_akun')
                        ->label('Nama Akun')
                        ->required()
                        ->readOnly(),

                    TextInput::make('keterangan')
                        ->label('Keterangan')
                        ->columnSpanFull(),

                    TextInput::make('banyak')
                        ->label('Kuantitas')
                        ->numeric()
                        ->required(),

                    TextInput::make('harga')
                        ->label('Harga Satuan')
                        ->numeric()
                        ->prefix('Rp')
                        ->required(),

                    Select::make('map')
                        ->label('Posisi')
                        ->options(['D' => 'Debit', 'K' => 'Kredit'])
                        ->required(),
                ])
            ])
            ->fillForm(fn(array $arguments) => JurnalModel::find($arguments['id'])?->toArray() ?? [])
            ->action(function (array $data, array $arguments): void {
                $record = JurnalModel::find($arguments['id']);
                if ($record) {
                    $record->update($data);
                    Notification::make()->title('Data riwayat berhasil diperbarui')->success()->send();
                }
            });
    }

    public function deleteHistoryAction(): Action
    {
        return Action::make('deleteHistory')
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
}
