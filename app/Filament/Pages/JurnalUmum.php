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
    protected static UnitEnum|string|null $navigationGroup = 'Jurnal';
    protected static ?string $title = 'Jurnal Umum';

    public $tgl, $jurnal, $no_akun, $nama_akun, $keterangan, $harga, $banyak = 1, $map = 'd';
    public $items = [];

    public bool $wasBalanced = false;

    // ─────────────────────────────────────────────────────────────
    // FILTER & PAGINATION PROPERTIES
    // ─────────────────────────────────────────────────────────────

    public $filterTglDariInput  = '';
    public $filterTglSampaiInput = '';
    public $filterTglDari  = '';
    public $filterTglSampai = '';
    public int $perPage = 50;
    public bool $hasMorePages = true;
    public bool $isLoadingMore = false;

    // ─────────────────────────────────────────────────────────────
    // MOUNT
    // ─────────────────────────────────────────────────────────────
    public function mount()
    {
        $this->tgl    = session()->get('jurnal_draft_tgl', now()->format('Y-m-d'));
        $this->items  = session()->get('jurnal_draft_items', []);
        $this->map    = session()->get('jurnal_draft_map', 'd');
        $this->banyak = session()->get('jurnal_draft_banyak', 1);
        $this->harga  = session()->get('jurnal_draft_harga', '');

        $this->syncJurnalNumber();
        $this->updateAutoBalanceSuggestion(false);
    }

    /**
     * Logika sinkronisasi nomor jurnal secara cerdas.
     * Jika draf kosong atau tidak balance, gunakan nomor jurnal yang sedang dikerjakan.
     * Jika draf sudah balance, siapkan nomor jurnal berikutnya.
     */
    protected function syncJurnalNumber()
    {
        if (empty($this->items)) {
            $last = JurnalModel::max('jurnal');
            $this->jurnal = $last ? $last + 1 : 1;
        } else {
            $draft = collect($this->items);
            $totalDebit  = (float) $draft->filter(fn($i) => strtolower($i['map']) === 'd')->sum('total');
            $totalKredit = (float) $draft->filter(fn($i) => strtolower($i['map']) === 'k')->sum('total');
            $isBalanced = abs($totalDebit - $totalKredit) < 0.01;

            if ($isBalanced) {
                // Jika balance, nomor jurnal untuk input selanjutnya adalah Max di draf + 1
                $this->jurnal = (int) $draft->max('jurnal') + 1;
            } else {
                // Jika belum balance, tetap gunakan nomor jurnal yang ada di draf
                $this->jurnal = $draft->first()['jurnal'];
            }
        }
        session()->put('jurnal_draft_kode', $this->jurnal);
    }

    protected function generateKodeJurnal()
    {
        // Dialihkan ke syncJurnalNumber untuk konsistensi draf
        $this->syncJurnalNumber();
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

    protected function getViewData(): array
    {
        $sub  = SubAnakAkun::selectRaw("kode_sub_anak_akun as no, nama_sub_anak_akun as nama");
        $anak = AnakAkun::selectRaw("kode_anak_akun as no, nama_anak_akun as nama");

        $query = JurnalModel::latest('id');

        if (!empty($this->filterTglDari)) {
            $query->whereDate('tgl', '>=', $this->filterTglDari);
        }

        if (!empty($this->filterTglSampai)) {
            $query->whereDate('tgl', '<=', $this->filterTglSampai);
        }

        $data = $query->limit($this->perPage + 1)->get();
        $this->hasMorePages = $data->count() > $this->perPage;
        $historyJurnals = $data->take($this->perPage);

        $totalsQuery = JurnalModel::query();
        if (!empty($this->filterTglDari)) {
            $totalsQuery->whereDate('tgl', '>=', $this->filterTglDari);
        }
        if (!empty($this->filterTglSampai)) {
            $totalsQuery->whereDate('tgl', '<=', $this->filterTglSampai);
        }
        $allForTotals = $totalsQuery->get(['map', 'banyak', 'harga']);

        $totalDebitDB  = $allForTotals->filter(fn($j) => strtolower($j->map) === 'd')->sum(fn($j) => $j->banyak * $j->harga);
        $totalKreditDB = $allForTotals->filter(fn($j) => strtolower($j->map) === 'k')->sum(fn($j) => $j->banyak * $j->harga);
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

    public function applyFilter(): void
    {
        $this->filterTglDari   = $this->filterTglDariInput;
        $this->filterTglSampai = $this->filterTglSampaiInput;
        $this->perPage         = 50;
        $this->hasMorePages    = true;
    }

    public function resetFilter(): void
    {
        $this->filterTglDariInput   = '';
        $this->filterTglSampaiInput = '';
        $this->filterTglDari        = '';
        $this->filterTglSampai      = '';
        $this->perPage              = 50;
        $this->hasMorePages         = true;
    }

    public function loadMore(): void
    {
        if (!$this->hasMorePages) return;
        $this->perPage += 50;
    }

    protected function isDraftBalanced(): bool
    {
        if (empty($this->items)) return false;

        $data        = collect($this->items);
        $totalDebit  = (float) $data->filter(fn($i) => strtolower($i['map']) === 'd')->sum('total');
        $totalKredit = (float) $data->filter(fn($i) => strtolower($i['map']) === 'k')->sum('total');

        return abs($totalDebit - $totalKredit) < 0.01;
    }

    /**
     * Logic Auto-Balance Fleksibel
     * @param bool $suggestMap Jika true, sistem akan menyarankan posisi (D/K) penyeimbang.
     */
    private function updateAutoBalanceSuggestion(bool $suggestMap = true)
    {
        if (empty($this->items)) {
            return;
        }

        $data        = collect($this->items);
        $totalDebit  = (float) $data->filter(fn($i) => strtolower($i['map']) === 'd')->sum('total');
        $totalKredit = (float) $data->filter(fn($i) => strtolower($i['map']) === 'k')->sum('total');
        $selisih     = $totalDebit - $totalKredit;

        if (abs($selisih) > 0.01) {
            // Isi otomatis harga dengan nilai selisih
            $this->harga  = abs($selisih);
            $this->banyak = 1;

            // Berikan saran posisi HANYA JIKA diminta (setelah addItem atau saat pilih akun pertama kali)
            if ($suggestMap) {
                $this->map = ($selisih > 0) ? 'k' : 'd';
            }
        } else {
            // Jika sudah balance, kosongkan harga saran
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

        // Saat pilih akun, hitung selisih harga tapi jangan paksa pindah posisi Map.
        // User tetap bisa memilih sendiri mau input Debit atau Kredit lagi.
        $this->updateAutoBalanceSuggestion(false);
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
            'map'        => strtolower($this->map),
        ];

        // Cek apakah draf sekarang sudah balance
        if ($this->isDraftBalanced()) {
            $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga']);
            $this->banyak      = 1;
            $this->map         = 'd';
            $this->wasBalanced = true;
            // Update nomor jurnal ke angka baru karena voucher sebelumnya sudah selesai (balance)
            $this->syncJurnalNumber();
        } else {
            // Belum balance: bersihkan input field akun saja, pertahankan jurnal number
            $this->reset(['no_akun', 'nama_akun', 'keterangan']);
            // Sarankan posisi Map penyeimbang untuk baris berikutnya
            $this->updateAutoBalanceSuggestion(true);
        }

        $this->persistDraftState();
    }

    public function removeItem(int $index)
    {
        if (isset($this->items[$index])) {
            array_splice($this->items, $index, 1);

            // Setelah penghapusan, cek ulang nomor jurnal.
            // Jika draf menjadi unbalanced, nomor jurnal harus kembali ke nomor yang ada di draf.
            $this->syncJurnalNumber();
            $this->updateAutoBalanceSuggestion(true);
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
        $this->map    = 'd';

        $this->perPage      = 50;
        $this->hasMorePages = true;

        // Reset ke nomor jurnal terbaru di DB
        $this->syncJurnalNumber();

        Notification::make()->title('Jurnal Berhasil Diposting')->success()->send();
    }

    public function resetForm()
    {
        $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga']);
        $this->banyak = 1;
        $this->map    = 'd';
        $this->updateAutoBalanceSuggestion(false);
        $this->persistDraftState();
    }

    public function editHistoryAction(): Action
    {
        return Action::make('editHistory')
            ->modalHeading('Edit Transaksi Riwayat')
            ->modalSubmitActionLabel('Simpan Perubahan')
            ->form([
                Grid::make(2)->schema([
                    DatePicker::make('tgl')->label('Tanggal')->required()->native(false),
                    TextInput::make('jurnal')->label('No. Jurnal')->required(),
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
                    TextInput::make('nama_akun')->label('Nama Akun')->required()->readOnly(),
                    TextInput::make('keterangan')->label('Keterangan')->columnSpanFull(),
                    TextInput::make('banyak')->label('Kuantitas')->numeric()->required(),
                    TextInput::make('harga')->label('Harga Satuan')->numeric()->prefix('Rp')->required(),
                    Select::make('map')
                        ->label('Posisi')
                        ->options(['d' => 'Debit', 'k' => 'Kredit'])
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
