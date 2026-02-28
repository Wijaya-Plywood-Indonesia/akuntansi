<?php

namespace App\Filament\Pages;

use App\Models\AnakAkun;
use App\Models\JurnalUmum as JurnalModel;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class JurnalUmum extends Page
{
    protected string $view = 'filament.pages.jurnal-umum';
    protected static UnitEnum|string|null $navigationGroup = 'jurnal';
    protected static ?string $title = 'Jurnal Umum';

    // Properti Form (Dihubungkan via @entangle)
    public $tgl, $jurnal, $no_akun, $nama_akun, $keterangan, $banyak, $harga, $map = 'D';

    // Properti Draft (Tersimpan di Session)
    public $items = [];

    /**
     * Inisialisasi data awal
     */
    public function mount()
    {
        $this->tgl = now()->format('Y-m-d');
        $this->items = session()->get('jurnal_draft_items', []);
        $this->updateAutoBalanceSuggestion();
    }

    /**
     * Menyediakan data untuk View.
     * Perbaikan: Menggunakan Relasi yang benar (subAkun) atau tanpa eager loading jika nama_akun sudah ada di tabel.
     */
    protected function getViewData(): array
    {
        return [
            'accounts' => AnakAkun::select('kode_anak_akun as no', 'nama_anak_akun as nama')->get(),
            // Perbaikan di sini: Menghapus with('akun') karena tidak ada di Model
            'historyJurnals' => JurnalModel::latest('id')->take(20)->get(),
        ];
    }

    /**
     * Fitur Auto-Balance saat pilih akun
     */
    public function updatedNoAkun($value)
    {
        if (blank($value)) {
            $this->nama_akun = '';
            $this->harga = '';
            return;
        }

        $akun = AnakAkun::where('kode_anak_akun', $value)->select('nama_anak_akun')->first();
        $this->nama_akun = $akun ? $akun->nama_anak_akun : '';

        $this->updateAutoBalanceSuggestion();
    }

    /**
     * Kalkulasi selisih untuk saran input
     */
    private function updateAutoBalanceSuggestion()
    {
        $data = collect($this->items);
        $totalDebit = (float) $data->whereIn('map', ['D', 'debit', 'd'])->sum('total');
        $totalKredit = (float) $data->whereIn('map', ['K', 'kredit', 'k'])->sum('total');
        $selisih = $totalDebit - $totalKredit;

        if ($selisih != 0) {
            $this->harga = abs($selisih);
            $this->map = ($selisih > 0) ? 'K' : 'D';
            $this->banyak = 1;
        } else {
            if (!empty($this->items)) {
                $this->harga = '';
                $this->banyak = '';
            }
            $this->map = 'D';
        }
    }

    /**
     * Tambah ke Draft
     */
    public function addItem()
    {
        $this->validate([
            'no_akun' => 'required',
            'harga' => 'required|numeric',
        ]);

        $qty = (float)($this->banyak ?: 1);
        $valHarga = (float)$this->harga;

        $this->items[] = [
            'tgl' => $this->tgl,
            'jurnal' => $this->jurnal ?: 'JR-' . date('His'),
            'no_akun' => $this->no_akun,
            'nama_akun' => $this->nama_akun,
            'keterangan' => $this->keterangan,
            'banyak' => $qty,
            'harga' => $valHarga,
            'total' => $qty * $valHarga,
            'map' => $this->map,
        ];

        session()->put('jurnal_draft_items', $this->items);

        // Reset Form sesuai aturan seimbang/tidak
        $data = collect($this->items);
        $diff = (float) $data->whereIn('map', ['D', 'd'])->sum('total') - (float) $data->whereIn('map', ['K', 'k'])->sum('total');

        if (abs($diff) < 0.01) {
            $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga', 'jurnal']);
            $this->map = 'D';
        } else {
            $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga']);
            $this->updateAutoBalanceSuggestion();
        }

        Notification::make()->title('Ditambahkan ke draft')->success()->send();
    }

    /**
     * Hapus Item Draft
     */
    public function removeItem($index)
    {
        if (isset($this->items[$index])) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);

            if (empty($this->items)) {
                session()->forget('jurnal_draft_items');
                $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga', 'jurnal']);
                $this->map = 'D';
            } else {
                session()->put('jurnal_draft_items', $this->items);
                $this->updateAutoBalanceSuggestion();
            }
        }
    }

    /**
     * Simpan Draft ke Database
     */
    public function saveJurnal()
    {
        if (empty($this->items)) return;

        $data = collect($this->items);
        $diff = (float) $data->whereIn('map', ['D', 'd'])->sum('total') - (float) $data->whereIn('map', ['K', 'k'])->sum('total');

        if (abs($diff) > 0.01) {
            Notification::make()->title('Gagal: Belum Balance')->danger()->send();
            return;
        }

        try {
            DB::transaction(function () {
                foreach ($this->items as $item) {
                    JurnalModel::create([
                        'tgl' => $item['tgl'],
                        'jurnal' => (int) $item['jurnal'],
                        'no_akun' => $item['no_akun'],
                        'nama_akun' => $item['nama_akun'],
                        'keterangan' => $item['keterangan'],
                        'banyak' => $item['banyak'],
                        'harga' => $item['harga'],
                        'map' => $item['map'],
                    ]);
                }
            });

            $this->clearDraft();
            Notification::make()->title('Jurnal Berhasil Diposting')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Error Database')->body($e->getMessage())->danger()->send();
        }
    }

    public function clearDraft()
    {
        $this->items = [];
        session()->forget('jurnal_draft_items');
        $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga', 'jurnal']);
        $this->map = 'D';
    }

    public function resetForm()
    {
        $this->reset(['no_akun', 'nama_akun', 'keterangan', 'banyak', 'harga', 'jurnal']);
        $this->map = 'D';
    }
}
