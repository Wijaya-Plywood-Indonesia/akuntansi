<?php

namespace App\Filament\Pages;

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
    public $total = '';
    public $banyak = '';
    public $mm = '';
    public $m3 = '';
    public $hit_kbk = '';
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
        $this->banyak     = session()->get('jurnal_draft_banyak', '');
        $this->harga      = session()->get('jurnal_draft_harga', '');
        $this->no_dokumen = session()->get('jurnal_draft_nodok', '');
        $this->nama       = session()->get('jurnal_draft_nama', '');
        $this->mm         = session()->get('jurnal_draft_mm', '');
        $this->m3         = session()->get('jurnal_draft_m3', '');
        $this->hit_kbk    = session()->get('jurnal_draft_hitkbk', '');
        $this->jurnal     = session()->get('jurnal_draft_kode', $this->getNextJurnalNumber());
        $this->total      = session()->get('jurnal_draft_total', '');

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
        session()->put('jurnal_draft_total', $this->total);
    }

    // ---
    // VIEW DATA
    // ---
    protected function getViewData(): array
    {
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
            'accounts'          => SubAnakAkun::selectRaw("kode_sub_anak_akun as no, nama_sub_anak_akun as nama")->get(),
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

        $this->nama_akun = SubAnakAkun::where('kode_sub_anak_akun', $value)
            ->first()
            ?->nama_sub_anak_akun ?? '';

        $this->persistDraftState();
    }

    public function updatedHitKbk($value): void
    {
        $this->persistDraftState();
    }

    // ---
    // ADD ITEM
    // ---
    public function addItem(): void
    {
        $errors = [];

        if (blank($this->no_akun))   $errors[] = 'No. Akun wajib dipilih.';
        if (blank($this->nama_akun)) $errors[] = 'Nama Akun belum terisi.';
        
        $harga = blank($this->harga) ? 0.0 : (float) $this->harga;
        $total = blank($this->total) ? 0.0 : (float) $this->total;
        $banyak = blank($this->banyak) ? 0.0 : (float) $this->banyak;
        $m3 = blank($this->m3) ? 0.0 : (float) $this->m3;

        // Logika baru untuk hit_kbk kosong (null)
        if (blank($this->hit_kbk)) {
            // Jika salah satu saja yang diisi
            if ($harga < 0.01 && $total >= 0.01) {
                $harga = $total;
            } elseif ($total < 0.01 && $harga >= 0.01) {
                $total = $harga;
            }

            // Kuantitas (banyak) otomatis menjadi 1
            $banyak = 1.0;

            // Validasi kelayakan nominal
            if ($harga < 0.01) {
                $errors[] = 'Harga atau Total wajib diisi (minimal Rp 1).';
            }

            if (abs($total - $harga) >= 0.01) {
                $errors[] = 'Data tidak sesuai: Karena Hit KBK kosong, Total harus sama dengan Harga (Rp ' . number_format($harga, 0, ',', '.') . '), sedangkan Total diisi Rp ' . number_format($total, 0, ',', '.') . '.';
            }
        } else {
            // Jika hit_kbk tidak kosong (Banyak atau Kubikasi)
            if ($harga < 0.01) {
                $errors[] = 'Harga wajib diisi (minimal Rp 1).';
            }
            if ($total < 0.01) {
                $errors[] = 'Total wajib diisi (minimal Rp 1).';
            }

            if ($this->hit_kbk === 'b') {
                if ($banyak < 0.0001) {
                    $errors[] = 'Kuantitas (Banyak) harus diisi dan lebih dari 0.';
                } else {
                    $expected = $banyak * $harga;
                    if (abs($total - $expected) >= 0.01) {
                        $errors[] = 'Data tidak sesuai: Kuantitas (' . $banyak . ') x Harga (Rp ' . number_format($harga, 0, ',', '.') . ') = Rp ' . number_format($expected, 0, ',', '.') . ', sedangkan Total diisi Rp ' . number_format($total, 0, ',', '.') . '.';
                    }
                }
            } elseif ($this->hit_kbk === 'm') {
                if ($m3 < 0.000001) {
                    $errors[] = 'Kubikasi (M3) harus diisi dan lebih dari 0.';
                } else {
                    $expected = $m3 * $harga;
                    if (abs($total - $expected) >= 0.01) {
                        $errors[] = 'Data tidak sesuai: Kubikasi (' . $m3 . ') x Harga (Rp ' . number_format($harga, 0, ',', '.') . ') = Rp ' . number_format($expected, 2, ',', '.') . ', sedangkan Total diisi Rp ' . number_format($total, 0, ',', '.') . '.';
                    }
                }
            }
        }

        if (!empty($errors)) {
            $this->dispatch('toast', type: 'error', title: 'Validasi Gagal', msg: $errors[0]);
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

        // ── Reset all fields to defaults after adding to draft ───
        $this->reset(['no_akun', 'nama_akun', 'nama', 'keterangan', 'mm', 'no_dokumen']);
        $this->harga   = '';
        $this->banyak  = '';
        $this->map     = 'd';
        $this->hit_kbk = '';
        $this->m3      = '';
        $this->total   = '';

        $this->dispatch('toast', type: 'info', title: 'Item Ditambahkan', msg: 'Item berhasil masuk ke draft.');
        $this->dispatch('form-reset');

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
            'jurnal_draft_total',
        ]);

        $this->items      = [];
        $this->wasBalanced = false;
        $this->reset(['no_akun', 'nama_akun', 'nama', 'keterangan', 'mm']);
        $this->harga   = '';
        $this->banyak  = '';
        $this->map     = 'd';
        $this->hit_kbk = '';
        $this->m3      = '';
        $this->total   = '';
        $this->perPage = 50;
        $this->hasMorePages = true;

        // Nomor jurnal baru diambil dari DB — hanya terjadi setelah posting
        $this->jurnal = $this->getNextJurnalNumber();

        $this->dispatch('toast', type: 'success', title: 'Jurnal Diposting!', msg: 'Semua entri berhasil disimpan ke database.');
        $this->dispatch('form-reset');
    }

    // ---
    // RESET FORM
    // ---
    public function resetForm(): void
    {
        $this->reset(['no_akun', 'nama_akun', 'nama', 'keterangan', 'mm']);
        $this->harga   = '';
        $this->banyak  = '';
        $this->map     = 'd';
        $this->hit_kbk = '';
        $this->m3      = '';
        $this->total   = '';
        $this->persistDraftState();
        $this->dispatch('form-reset');
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

    public function editDraftAction(): Action
    {
        return Action::make('editDraft')
            ->modalHeading('Edit Draft Transaksi')
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
                            return SubAnakAkun::all()->mapWithKeys(fn($item) => [
                                $item->kode_sub_anak_akun => "{$item->kode_sub_anak_akun} - {$item->nama_sub_anak_akun}"
                            ]);
                        })
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            $name = SubAnakAkun::where('kode_sub_anak_akun', $state)
                                ->first()
                                ?->nama_sub_anak_akun ?? '';
                            $set('nama_akun', $name);
                        }),
                    TextInput::make('nama_akun')->label('Nama Akun')->required()->readOnly(),
                    
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
                    
                    TextInput::make('harga')->label('Harga Satuan')->numeric()->prefix('Rp')->required()->columnSpanFull(),
                ])
            ])
            ->fillForm(function (array $arguments) {
                return $this->items[$arguments['index']];
            })
            ->action(function (array $data, array $arguments): void {
                $index = $arguments['index'];

                
                $data['hit_kbk']    = $data['hit_kbk'] ?? '';
                $data['keterangan'] = $data['keterangan'] ?? '';

                // Update data di array lokal
                $this->items[$index] = array_merge($this->items[$index], $data);

                // Kalkulasi total dengan aman
                $hit_kbk = $data['hit_kbk'];
                $banyak  = (float) ($data['banyak'] ?? 0);
                $m3      = (float) ($data['m3'] ?? 0);
                $harga   = (float) ($data['harga'] ?? 0);

                $this->items[$index]['total'] = match ($hit_kbk) {
                    'b' => $banyak * $harga,
                    'm' => $m3 * $harga,
                    default => $harga,
                };

                $this->persistDraftState();
                Notification::make()->title('Draft berhasil diperbarui')->success()->send();
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
