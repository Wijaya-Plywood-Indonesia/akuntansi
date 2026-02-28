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

    /**
     * Menandai apakah draft sudah balance sebelumnya.
     * Digunakan untuk mendeteksi kapan harus reset jurnal (setelah balance tercapai).
     */
    public bool $wasBalanced = false;

    public function mount()
    {
        // Pulihkan tgl dari session, default hari ini jika belum ada
        $this->tgl = session()->get('jurnal_draft_tgl', now()->format('Y-m-d'));

        // Pulihkan items dari session
        $this->items = session()->get('jurnal_draft_items', []);

        // Pulihkan map & banyak dari session
        $this->map    = session()->get('jurnal_draft_map', 'D');
        $this->banyak = session()->get('jurnal_draft_banyak', 1);
        $this->harga  = session()->get('jurnal_draft_harga', '');

        /**
         * Kode jurnal:
         * - Jika session ada → pakai (draft sedang berjalan, tidak boleh berubah saat refresh)
         * - Jika tidak ada   → generate dari max(DB) + 1, lalu simpan ke session
         */
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

        // Simpan ke session agar tidak berubah saat refresh
        session()->put('jurnal_draft_kode', $this->jurnal);
    }

    /**
     * Simpan semua state draft ke session sekaligus.
     * Dipanggil setiap kali ada perubahan state yang perlu dipertahankan.
     */
    private function persistDraftState(): void
    {
        session()->put('jurnal_draft_items',  $this->items);
        session()->put('jurnal_draft_kode',   $this->jurnal);
        session()->put('jurnal_draft_tgl',    $this->tgl);
        session()->put('jurnal_draft_map',    $this->map);
        session()->put('jurnal_draft_harga',  $this->harga);
        session()->put('jurnal_draft_banyak', $this->banyak);
    }

    /**
     * Menyediakan data untuk View.
     */
    protected function getViewData(): array
    {
        $sub  = SubAnakAkun::selectRaw("kode_sub_anak_akun as no, nama_sub_anak_akun as nama");
        $anak = AnakAkun::selectRaw("kode_anak_akun as no, nama_anak_akun as nama");

        return [
            'accounts'       => $sub->unionAll($anak)->get(),
            'historyJurnals' => JurnalModel::latest('id')->take(20)->get(),
        ];
    }

    /**
     * Cek apakah draft saat ini balanced.
     */
    protected function isDraftBalanced(): bool
    {
        if (empty($this->items)) return false;

        $data        = collect($this->items);
        $totalDebit  = (float) $data->whereIn('map', ['D', 'debit', 'd'])->sum('total');
        $totalKredit = (float) $data->whereIn('map', ['K', 'kredit', 'k'])->sum('total');

        return abs($totalDebit - $totalKredit) < 0.01;
    }

    /**
     * Auto-balance: mengisi harga & posisi (D/K) berdasarkan selisih draft.
     * Dipanggil setiap kali item ditambah atau nomor akun berubah.
     */
    private function updateAutoBalanceSuggestion()
    {
        if (empty($this->items)) {
            // Draft masih kosong, tidak ada auto-balance
            return;
        }

        $data        = collect($this->items);
        $totalDebit  = (float) $data->whereIn('map', ['D', 'debit', 'd'])->sum('total');
        $totalKredit = (float) $data->whereIn('map', ['K', 'kredit', 'k'])->sum('total');
        $selisih     = $totalDebit - $totalKredit;

        if (abs($selisih) > 0.01) {
            // Draft belum balance → suggestikan nilai & posisi penyeimbang
            $this->harga  = abs($selisih);
            $this->map    = ($selisih > 0) ? 'K' : 'D';
            $this->banyak = 1;
        } else {
            // Draft sudah balance → kosongkan input harga
            $this->harga  = '';
            $this->banyak = 1;
        }
    }

    /**
     * Dipanggil oleh Livewire saat properti $no_akun berubah (reaktivitas otomatis).
     */
    public function updatedNoAkun($value)
    {
        if (blank($value)) {
            $this->nama_akun = '';
            $this->harga     = '';
            $this->persistDraftState();
            return;
        }

        // Cari nama akun dari SubAnakAkun atau AnakAkun
        $this->nama_akun = SubAnakAkun::where('kode_sub_anak_akun', $value)->first()?->nama_sub_anak_akun
            ?? AnakAkun::where('kode_anak_akun', $value)->first()?->nama_anak_akun
            ?? '';

        // Setelah user memilih akun, terapkan auto-balance suggestion
        $this->updateAutoBalanceSuggestion();

        // Persist harga & map hasil auto-balance
        $this->persistDraftState();
    }

    /**
     * Menambahkan baris ke draft.
     */
    public function addItem()
    {
        $this->validate([
            'no_akun' => 'required',
            'harga'   => 'required|numeric|min:0.01',
        ]);

        // Tambah item ke draft
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

        // Cek status balance SETELAH item ditambahkan
        if ($this->isDraftBalanced()) {
            // ✅ Draft sudah balance → reset detail, jurnal +1 untuk set berikutnya
            $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga']);
            $this->banyak      = 1;
            $this->map         = 'D';
            $this->wasBalanced = true;

            // Increment jurnal +1 agar entri berikutnya pakai nomor baru
            $this->jurnal = (int) $this->jurnal + 1;
        } else {
            // ⏳ Draft belum balance → hanya reset input detail, pertahankan tgl & jurnal
            $this->reset(['no_akun', 'nama_akun', 'keterangan']);
            $this->banyak = 1;
            // Jalankan auto-balance untuk mengisi harga & posisi berikutnya
            $this->updateAutoBalanceSuggestion();
        }

        // Persist semua state setelah perubahan
        $this->persistDraftState();
    }

    /**
     * Menghapus item dari draft berdasarkan index.
     */
    public function removeItem(int $index)
    {
        if (isset($this->items[$index])) {
            array_splice($this->items, $index, 1);

            // Setelah hapus, perbarui auto-balance suggestion
            $this->updateAutoBalanceSuggestion();

            $this->persistDraftState();
        }
    }

    /**
     * Posting semua item draft ke database.
     * Hanya bisa dieksekusi saat draft balanced.
     */
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

        // Hapus semua session draft setelah posting berhasil
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

        // Reset form penuh setelah posting
        $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga']);
        $this->banyak = 1;
        $this->map    = 'D';

        // Generate kode jurnal baru dari DB dan simpan ke session
        $this->generateKodeJurnal();

        Notification::make()->title('Jurnal Berhasil Diposting')->success()->send();
    }

    /**
     * Reset form input saja. Draft $items tetap tidak terhapus.
     */
    public function resetForm()
    {
        $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga']);
        $this->banyak = 1;
        $this->map    = 'D';

        // Jalankan auto-balance ulang jika masih ada item di draft
        $this->updateAutoBalanceSuggestion();

        $this->persistDraftState();
    }

    // ==========================================
    // MODAL EDIT & DELETE (tidak ada perubahan)
    // ==========================================

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
                        ->options([
                            'D' => 'Debit',
                            'K' => 'Kredit',
                        ])
                        ->required(),
                ])
            ])
            ->fillForm(function (array $arguments) {
                return JurnalModel::find($arguments['id'])?->toArray() ?? [];
            })
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
