<?php

namespace App\Filament\Pages;

use App\Models\AnakAkun;
use App\Models\SubAnakAkun;
use App\Models\JurnalUmum as JurnalModel;
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
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class JurnalUmum extends Page implements HasActions, HasForms
{
    use HasPageShield;
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.pages.jurnal-umum';
    protected static UnitEnum|string|null $navigationGroup = 'Jurnal & Akuntansi';
    protected static ?string $title = 'Jurnal Umum';

    public $tgl, $jurnal, $no_dokumen, $no_akun, $nama_akun, $nama, $keterangan;
    public $harga = '';
    public $banyak = '1';
    public $mm = '';
    public $m3 = '';
    public $hit_kbk = 'b';
    public $map = 'd';
    public $items = [];

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

    // ---
    // MOUNT
    // ---
    public function mount(): void
    {
        $this->tgl        = session()->get('jurnal_draft_tgl', now()->format('Y-m-d'));
        $this->items      = session()->get('jurnal_draft_items', []);
        $this->banyak     = session()->get('jurnal_draft_banyak', strtolower($this->hit_kbk) === 'b' ? 1 : '');
        $this->harga      = session()->get('jurnal_draft_harga', '');
        $this->no_dokumen = session()->get('jurnal_draft_nodok', '');
        $this->nama       = session()->get('jurnal_draft_nama', '');
        $this->mm         = session()->get('jurnal_draft_mm', '');
        $this->m3         = session()->get('jurnal_draft_m3', '');
        $this->hit_kbk    = session()->get('jurnal_draft_hitkbk', 'b');
        $this->jurnal     = session()->get('jurnal_draft_kode', $this->getNextJurnalNumber());

        $savedMap   = session()->get('jurnal_draft_map', 'd');
        $this->map  = in_array(strtolower($savedMap), ['d', 'k']) ? strtolower($savedMap) : 'd';
    }

    // ---
    // HELPER: ambil nomor jurnal berikutnya dari DB
    // Hanya dipanggil manual — tidak otomatis setelah balance
    // ---
    protected function getNextJurnalNumber(): int
    {
        $last = JurnalModel::max('jurnal');
        return $last ? (int) $last + 1 : 1;
    }

    // ---
    // PERSIST SESSION
    // ---
    private function persistDraftState(): void
    {
        session()->put('jurnal_draft_items',   $this->items);
        session()->put('jurnal_draft_kode',    $this->jurnal);
        session()->put('jurnal_draft_tgl',     $this->tgl);
        session()->put('jurnal_draft_map',     $this->map);
        session()->put('jurnal_draft_harga',   $this->harga);
        session()->put('jurnal_draft_banyak',  $this->banyak);
        session()->put('jurnal_draft_nodok',   $this->no_dokumen);
        session()->put('jurnal_draft_nama',    $this->nama);
        session()->put('jurnal_draft_mm',      $this->mm);
        session()->put('jurnal_draft_m3',      $this->m3);
        session()->put('jurnal_draft_hitkbk',  $this->hit_kbk);
    }

    // ---
    // VIEW DATA
    // ---
    protected function getViewData(): array
    {
        $sub  = SubAnakAkun::selectRaw("kode_sub_anak_akun as no, nama_sub_anak_akun as nama");
        $anak = AnakAkun::selectRaw("kode_anak_akun as no, nama_anak_akun as nama");

        $query = JurnalModel::latest('id');
        if (!empty($this->filterTglDari))
            $query->whereDate('tgl', '>=', $this->filterTglDari);
        if (!empty($this->filterTglSampai))
            $query->whereDate('tgl', '<=', $this->filterTglSampai);

        $data = $query->limit($this->perPage + 1)->get();
        $this->hasMorePages = $data->count() > $this->perPage;
        $historyJurnals     = $data->take($this->perPage);

        $totalsQuery = JurnalModel::query();
        if (!empty($this->filterTglDari))
            $totalsQuery->whereDate('tgl', '>=', $this->filterTglDari);
        if (!empty($this->filterTglSampai))
            $totalsQuery->whereDate('tgl', '<=', $this->filterTglSampai);

        $allForTotals = $totalsQuery->get(['map', 'hit_kbk', 'banyak', 'm3', 'harga']);

        $calcTotal = fn($j) => match (strtolower($j->hit_kbk ?? '')) {
            'b'     => (float) $j->banyak * (float) $j->harga,
            'm'     => (float) $j->m3     * (float) $j->harga,
            default => (float) $j->harga,
        };

        $totalDebitDB      = $allForTotals->filter(fn($j) => strtolower($j->map) === 'd')->sum($calcTotal);
        $totalKreditDB     = $allForTotals->filter(fn($j) => strtolower($j->map) === 'k')->sum($calcTotal);
        $isHistoryBalanced = abs($totalDebitDB - $totalKreditDB) < 0.01;
        $selisihDB         = abs($totalDebitDB - $totalKreditDB);

        return [
            'accounts'          => $sub->unionAll($anak)->get(),
            'historyJurnals'    => $historyJurnals,
            'totalDebitDB'      => $totalDebitDB,
            'totalKreditDB'     => $totalKreditDB,
            'isHistoryBalanced' => $isHistoryBalanced,
            'selisihDB'         => $selisihDB,
        ];
    }

    // ---
    // FILTER
    // ---
    public function applyFilter(): void
    {
        $this->filterTglDari   = $this->filterTglDariInput;
        $this->filterTglSampai = $this->filterTglSampaiInput;
        $this->perPage         = 50;
        $this->hasMorePages    = true;
        $this->selectedIds     = [];
        $this->selectAll       = false;
    }

    public function resetFilter(): void
    {
        $this->filterTglDariInput  = '';
        $this->filterTglSampaiInput = '';
        $this->filterTglDari       = '';
        $this->filterTglSampai     = '';
        $this->perPage             = 50;
        $this->hasMorePages        = true;
        $this->selectedIds         = [];
        $this->selectAll           = false;
    }

    public function loadMore(): void
    {
        if (!$this->hasMorePages) return;
        $this->perPage  += 50;
        $this->selectAll = false;
    }

    // ---
    // BALANCE CHECK — tetap ada, hanya untuk tombol Posting
    // ---
    protected function isDraftBalanced(): bool
    {
        if (empty($this->items)) return false;

        $data   = collect($this->items);
        $debit  = (float) $data->filter(fn($i) => strtolower($i['map']) === 'd')->sum('total');
        $kredit = (float) $data->filter(fn($i) => strtolower($i['map']) === 'k')->sum('total');

        return abs($debit - $kredit) < 0.01;
    }

    // ---
    // UPDATED NO AKUN
    // Dihapus: recalcAutoBalance — sekarang hanya update nama akun
    // ---
    public function updatedNoAkun($value): void
    {
        if (blank($value)) {
            $this->nama_akun = '';
            $this->persistDraftState();
            return;
        }

        $this->nama_akun = SubAnakAkun::where('kode_sub_anak_akun', $value)->first()?->nama_sub_anak_akun
            ?? AnakAkun::where('kode_anak_akun', $value)->first()?->nama_anak_akun
            ?? '';

        $this->persistDraftState();
    }

    public function updatedHitKbk($value): void
    {
        if (strtolower($value) === 'b') {
            // Mode banyak: quantity langsung default 1, kubikasi dikosongkan
            $this->banyak = 1;
            $this->m3     = '';
        } else {
            // Mode kubikasi atau lainnya: user isi sendiri semua
            $this->banyak = '';
            $this->m3     = '';
        }

        $this->persistDraftState();
    }

    // ---
    // ADD ITEM
    // ---
    public function addItem(): void
    {
        // ── Validasi field wajib dulu, early return ──────────────
        $errors = [];

        if (blank($this->no_akun))   $errors[] = 'No. Akun wajib dipilih.';
        if (blank($this->nama_akun)) $errors[] = 'Nama Akun belum terisi.';
        if (blank($this->harga) || (float) $this->harga < 0.01)
            $errors[] = 'Harga wajib diisi (minimal Rp 1).';

        if (!empty($errors)) {
            foreach ($errors as $err) {
                $this->dispatch('toast', type: 'error', title: 'Validasi Gagal', msg: $err);
            }
            return; // stop di sini, jangan hitung total dulu
        }

        // ── Hitung total setelah harga dipastikan valid ──────────
        $harga  = (float) $this->harga;
        $banyak = (float) $this->banyak;
        $m3     = (float) $this->m3;

        $total = match ($this->hit_kbk) {
            'b'     => $banyak * $harga,
            'm'     => $m3     * $harga,
            default => $harga,
        };

        // ── Validasi total ───────────────────────────────────────
        if ($total < 0.01) {
            $this->dispatch('toast', type: 'error', title: 'Validasi Gagal', msg: match ($this->hit_kbk) {
                'b'     => 'Kuantitas harus lebih dari 0.',
                'm'     => 'Kubikasi (M3) harus lebih dari 0.',
                default => 'Harga harus lebih dari 0.',
            });
            return;
        }

        // ── Simpan ke draft ──────────────────────────────────────
        $this->items[] = [
            'tgl'        => $this->tgl,
            'jurnal'     => $this->jurnal,
            'no_dokumen' => $this->no_dokumen,
            'no_akun'    => $this->no_akun,
            'nama_akun'  => $this->nama_akun,
            'nama'       => $this->nama,
            'mm'         => $this->mm === '' ? null : (int) $this->mm,
            'keterangan' => $this->keterangan,
            'hit_kbk'    => $this->hit_kbk,
            'banyak'     => $banyak,
            'm3'         => $m3,
            'harga'      => $harga,
            'total'      => $total,
            'map'        => strtolower($this->map),
        ];

        // ── Reset hanya field per-baris, bukan harga/banyak/map ─
        // User tetap kontrol penuh atas semua field
        $this->reset(['no_akun', 'nama_akun', 'nama', 'keterangan', 'mm', 'm3']);

        $this->dispatch('toast', type: 'info', title: 'Item Ditambahkan', msg: 'Item berhasil masuk ke draft.');

        $this->persistDraftState();
    }

    // ---
    // REMOVE ITEM
    // ---
    public function removeItem(int $index): void
    {
        if (!isset($this->items[$index])) return;

        array_splice($this->items, $index, 1);

        $this->dispatch('toast', type: 'error', title: 'Item Dihapus', msg: 'Item berhasil dihapus dari draft.');

        $this->persistDraftState();
    }



    // ---
    // SAVE JURNAL
    // ---
    public function saveJurnal(): void
    {
        if (empty($this->items) || !$this->isDraftBalanced()) return;

        try {
            DB::transaction(function () {
                foreach ($this->items as $item) {
                    JurnalModel::create([
                        ...$item,
                        'status'     => 'belum sinkron',
                        'created_by' => Auth::user()->name,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', title: 'Error Sistem', msg: 'Gagal menyimpan jurnal: ' . $e->getMessage());
            return;
        }

        session()->forget([
            'jurnal_draft_items',
            'jurnal_draft_kode',
            'jurnal_draft_tgl',
            'jurnal_draft_map',
            'jurnal_draft_harga',
            'jurnal_draft_banyak',
            'jurnal_draft_nodok',
            'jurnal_draft_nama',
            'jurnal_draft_mm',
            'jurnal_draft_m3',
            'jurnal_draft_hitkbk',
        ]);

        $this->items      = [];
        $this->wasBalanced = false;
        $this->reset(['no_akun', 'nama_akun', 'nama', 'keterangan', 'mm', 'm3']);
        $this->harga   = '';
        $this->banyak  = 1;
        $this->map     = 'd';
        $this->hit_kbk = 'b';
        $this->perPage = 50;
        $this->hasMorePages = true;

        // Nomor jurnal baru diambil dari DB — hanya terjadi setelah posting
        $this->jurnal = $this->getNextJurnalNumber();

        $this->dispatch('toast', type: 'success', title: 'Jurnal Diposting!', msg: 'Semua entri berhasil disimpan ke database.');
    }

    // ---
    // RESET FORM
    // ---
    public function resetForm(): void
    {
        $this->reset(['no_akun', 'nama_akun', 'nama', 'keterangan', 'mm', 'm3']);
        $this->harga   = '';
        $this->banyak  = '';
        $this->map     = 'd';
        $this->hit_kbk = 'b';
        $this->banyak  = 1;
        $this->m3      = '';
        $this->persistDraftState();
    }

    // ══════════════════════════════════════════════════════════
    // BULK DELETE
    // ══════════════════════════════════════════════════════════

    public function toggleSelectAll(array $ids): void
    {
        if ($this->selectAll) {
            $this->selectedIds = [];
            $this->selectAll   = false;
        } else {
            $this->selectedIds = $ids;
            $this->selectAll   = true;
        }
    }

    public function toggleSelected(int $id): void
    {
        if (in_array($id, $this->selectedIds)) {
            $this->selectedIds = array_values(array_filter(
                $this->selectedIds,
                fn($i) => $i !== $id
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
        $this->selectAll   = false;

        $this->dispatch('toast', type: 'success', title: 'Berhasil Dihapus', msg: "{$count} transaksi berhasil dihapus.");
        $this->dispatch('bulk-delete-done');
    }

    // ══════════════════════════════════════════════════════════
    // EDIT & DELETE HISTORY ACTION
    // ══════════════════════════════════════════════════════════

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
                            $sub  = SubAnakAkun::all()->mapWithKeys(fn($item) => [
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
