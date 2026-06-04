<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Resources\Pembelians\PembeliansResource;
use App\Models\Barang;
use App\Models\DetailPembelian;
use App\Models\Pembelian as ModelsPembelian;
use App\Models\PembelianMetodePembayaran;
use App\Models\Supplier;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class Pembelian extends Page
{
    use WithFileUploads;

    protected static string $resource = PembeliansResource::class;

    protected string $view = 'filament.resources.pembelians.pages.pembelian';

    protected static ?string $title = 'Tambah Pembelian';

    public $nomor_nota;
    public $created_by;
    public $created_by_name;
    public $tanggal;

    public $supplier_id      = '';
    public $supplier_name;
    public $supplier_phone;
    public $supplier_address;
    public $is_new_supplier  = false;

    public $catatan;
    public $foto_nota = [];
    public $items = [];

    public string $search        = '';
    public array  $searchResults = [];
    public bool   $showDropdown  = false;

    public $sub_total    = 0;
    public $total_diskon = null;
    public $total_ppn    = null;
    public $ongkir       = null;
    public $biaya_lain   = null;

    public $payment_method    = PembelianMetodePembayaran::METODE_TUNAI;
    public $payment_amount    = null;
    public $tanggal_bayar;
    public $payment_reference = '';
    public $payment_catatan   = '';

    public function mount(): void
    {
        $this->created_by      = auth()->id();
        $this->created_by_name = auth()->user()->name ?? 'User';
        $this->tanggal         = now()->format('Y-m-d');
        $this->tanggal_bayar   = now()->format('Y-m-d');
    }

    public function updatedSearch(): void
    {
        $keyword = trim($this->search);

        if (strlen($keyword) < 1) {
            $this->searchResults = [];
            $this->openDropdown();
            return;
        }

        $this->searchResults = Barang::with('satuan')
            ->where('nama_barang', 'like', "%{$keyword}%")
            ->orWhere('kode_barang', 'like', "%{$keyword}%")
            ->orderBy('nama_barang')
            ->limit(10)
            ->get()
            ->map(fn($b) => [
                'id'          => $b->id,
                'kode_barang' => $b->kode_barang,
                'nama_barang' => $b->nama_barang,
                'harga_beli'  => floatval($b->harga_beli ?? 0),
                'satuan'      => is_object($b->satuan)
                    ? ($b->satuan->nama_satuan ?? $b->satuan->keterangan ?? 'Unit')
                    : ($b->satuan ?? 'Unit'),
            ])
            ->toArray();

        $this->showDropdown = !empty($this->searchResults);
    }

    public function openDropdown(): void
    {
        $this->showDropdown = true;

        if (empty(trim($this->search))) {
            $this->searchResults = Barang::with('satuan')
                ->orderBy('nama_barang', 'asc')
                ->limit(10)
                ->get()
                ->map(fn($b) => [
                    'id'          => $b->id,
                    'kode_barang' => $b->kode_barang,
                    'nama_barang' => $b->nama_barang,
                    'harga_beli'  => floatval($b->harga_beli ?? 0),
                    'satuan'      => is_object($b->satuan)
                        ? ($b->satuan->nama_satuan ?? 'Unit')
                        : ($b->satuan ?? 'Unit'),
                ])
                ->toArray();
        }
    }

    public function closeDropdown(): void
    {
        $this->showDropdown  = false;
        $this->searchResults = [];
    }

    public function selectBarang(int $barangId): void
    {
        $barang = Barang::with('satuan')->find($barangId);
        if (!$barang) return;

        $satuan = is_object($barang->satuan)
            ? ($barang->satuan->nama_satuan ?? $barang->satuan->keterangan ?? 'Unit')
            : ($barang->satuan ?? 'Unit');

        $harga = floatval($barang->harga_beli ?? 0);

        foreach ($this->items as $index => $item) {
            if ((int) ($item['barang_id'] ?? 0) === $barangId) {
                $qty        = floatval($this->items[$index]['qty'] ?? 0) + 1;
                $kubikasi   = floatval($this->items[$index]['kubikasi'] ?? 0);
                $hargaItem  = floatval($this->items[$index]['harga_beli'] ?? 0);
                $diskon     = floatval($this->items[$index]['diskon'] ?? 0);
                $hitungDari = $this->items[$index]['hitung_dari'] ?? 'qty';

                $subtotalLama = floatval($this->items[$index]['subtotal'] ?? 0);
                
                // Menentukan pengali (Qty atau M3)
                $pengali = ($hitungDari === 'm3') ? $kubikasi : $qty;
                $subtotalBaru = max(0.0, ($pengali * $hargaItem) - $diskon);

                $this->items[$index]['qty']      = $qty;
                $this->items[$index]['subtotal'] = $subtotalBaru;
                $this->sub_total = max(0.0, $this->sub_total - $subtotalLama + $subtotalBaru);

                $this->search       = '';
                $this->showDropdown = false;
                return;
            }
        }

        $subtotal = $harga;

        $this->items[] = [
            'barang_id'   => $barang->id,
            'kode_barang' => $barang->kode_barang,
            'nama_barang' => $barang->nama_barang,
            'satuan'      => $satuan,
            'qty'         => 1,
            'kubikasi'    => 0,
            'hitung_dari' => 'qty', // Default hitung berdasar Qty
            'harga_beli'  => $harga,
            'diskon'      => 0,
            'subtotal'    => $subtotal,
            'catatan'     => '',
        ];

        $this->sub_total    += $subtotal;
        $this->search        = '';
        $this->showDropdown  = false;
    }

    public function updatedSupplierId($value): void
    {
        $supplier = Supplier::find($value);
        if ($supplier) {
            $this->supplier_name    = $supplier->nama;
            $this->supplier_phone   = $supplier->telepon;
            $this->supplier_address = $supplier->alamat;
        } else {
            $this->reset(['supplier_name', 'supplier_phone', 'supplier_address']);
        }
    }

    public function addItem(): void
    {
        $this->items[] = [
            'barang_id'   => '',
            'kode_barang' => '',
            'nama_barang' => '',
            'satuan'      => '',
            'qty'         => 1,
            'kubikasi'    => 0,
            'hitung_dari' => 'qty',
            'harga_beli'  => null,
            'diskon'      => null,
            'subtotal'    => 0,
            'catatan'     => '',
        ];
    }

    public function updateItemField(int $index, float $qty, float $harga): void
    {
        if (!isset($this->items[$index])) return;

        $this->items[$index]['qty'] = $qty;
        $this->items[$index]['harga_beli'] = $harga;
        $this->recalculateSubTotal();
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) <= 0) return;

        $this->sub_total = max(0, $this->sub_total - floatval($this->items[$index]['subtotal'] ?? 0));

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedItems($value, $key): void
    {
        $parts = explode('.', $key);
        if (count($parts) < 2) return;

        [$index, $field] = [$parts[0], $parts[1]];

        if ($field === 'barang_id' && !empty($value)) {
            $barang = Barang::with('satuan')->find($value);
            if ($barang) {
                $this->items[$index]['kode_barang'] = $barang->kode_barang;
                $this->items[$index]['nama_barang'] = $barang->nama_barang;
                $this->items[$index]['satuan']      = is_object($barang->satuan)
                    ? ($barang->satuan->nama_satuan ?? $barang->satuan->keterangan ?? 'Unit')
                    : ($barang->satuan ?? 'Unit');
                $this->items[$index]['harga_beli']  = $barang->harga_beli;
            }
        }

        $this->recalculateSubTotal();
    }

    public function recalculateSubTotal(): void
    {
        $total = 0.0;

        foreach ($this->items as $index => $item) {
            $qty         = $this->parseNumber($item['qty'] ?? 0);
            $kubikasi    = $this->parseNumber($item['kubikasi'] ?? 0);
            $harga       = $this->parseNumber($item['harga_beli'] ?? 0);
            $diskon      = $this->parseNumber($item['diskon'] ?? 0);
            $hitung_dari = $item['hitung_dari'] ?? 'qty';

            // Menentukan pengali (Qty atau M3)
            $pengali = ($hitung_dari === 'm3') ? $kubikasi : $qty;
            $subtotal = max(0.0, ($pengali * $harga) - $diskon);

            $this->items[$index]['subtotal'] = $subtotal;
            $total += $subtotal;
        }

        $this->sub_total = $total;
    }

    #[Computed]
    public function grandTotal(): float
    {
        return (new ModelsPembelian)->hitungGrandTotal(
            subTotal: (float) $this->sub_total,
            totalDiskon: $this->parseNumber($this->total_diskon),
            totalPpn: $this->parseNumber($this->total_ppn),
            ongkir: $this->parseNumber($this->ongkir),
            biayaLain: $this->parseNumber($this->biaya_lain),
        );
    }

    public function getGrandTotalProperty(): float
    {
        return $this->grandTotal();
    }

    public function setBayarPas(): void
    {
        $grand = $this->grandTotal();
        $this->payment_amount = $grand > 0 ? $grand : null;
    }

    private function parseNumber(mixed $value): float
    {
        if (is_null($value) || $value === '') return 0.0;
        if (is_numeric($value)) return (float) $value;

        $str = (string) $value;

        $lastComma = strrpos($str, ',');
        $lastDot   = strrpos($str, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                $str = str_replace(',', '', $str);
            }
        } elseif ($lastComma !== false) {
            $afterComma = substr($str, $lastComma + 1);
            if (strlen($afterComma) === 3 && !str_contains(substr($str, 0, $lastComma), '.')) {
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace(',', '.', $str);
            }
        } elseif ($lastDot !== false) {
            $afterDot = substr($str, $lastDot + 1);
            if (strlen($afterDot) === 3 && substr_count($str, '.') === 1 && !str_contains($str, ',')) {
                $str = str_replace('.', '', $str);
            }
        }

        return (float) ($str ?: 0);
    }

    public function simpan(): void
    {
        $this->recalculateSubTotal();

        try {
            $this->validate([
                'nomor_nota'           => 'required',
                'tanggal'              => 'required|date',
                'supplier_id'          => 'required_unless:is_new_supplier,true',
                'supplier_name'        => 'required_if:is_new_supplier,true',
                'items'                => 'required|array|min:1',
                'items.*.barang_id'    => 'required',
                'items.*.qty'          => 'required|numeric|min:0.01',
                'items.*.kubikasi'     => 'nullable|numeric|min:0', // Validasi kubikasi
                'items.*.hitung_dari'  => 'required|in:qty,m3',     // Validasi pilihan dropdown
                'items.*.harga_beli'   => 'required|numeric|min:0',
            ], [
                'nomor_nota.required'         => 'Nomor nota/invoice wajib diisi.',
                'tanggal.required'            => 'Tanggal pembelian wajib diisi.',
                'supplier_id.required_unless' => 'Silakan pilih supplier atau tambah supplier baru.',
                'supplier_name.required_if'   => 'Nama supplier baru wajib diisi.',
                'items.required'              => 'Keranjang pembelian minimal harus berisi 1 barang.',
                'items.min'                   => 'Keranjang pembelian minimal harus berisi 1 barang.',
                'items.*.qty.required'        => 'Qty barang harus diisi.',
                'items.*.qty.min'             => 'Qty barang minimal 0.01.',
                'items.*.harga_beli.required' => 'Harga beli harus diisi.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->validator->errors()->all();
            Notification::make()
                ->title('Validasi Gagal')
                ->body(implode(' ', $errors))
                ->danger()
                ->send();
            return;
        }

        $grand   = $this->grandTotal();
        $dibayar = $this->parseNumber($this->payment_amount);

        if (($this->payment_method === PembelianMetodePembayaran::METODE_TUNAI || $this->payment_method === PembelianMetodePembayaran::METODE_TRANSFER) && $grand > 0) {
            if ($dibayar < $grand) {
                Notification::make()
                    ->title('Pembayaran Kurang')
                    ->body('Untuk metode pembayaran ' . ($this->payment_method === PembelianMetodePembayaran::METODE_TUNAI ? 'Tunai' : 'Transfer Bank') . ', nominal pembayaran harus lunas. Silakan gunakan metode Cicilan atau Down Payment (DP) jika ingin membayar sebagian.')
                    ->danger()
                    ->send();
                return;
            }
        }

        DB::beginTransaction();

        try {
            $final_supplier_id = !empty($this->supplier_id) ? $this->supplier_id : null;
            if ($this->is_new_supplier) {
                $newSupplier = Supplier::create([
                    'nama'    => $this->supplier_name,
                    'telepon' => $this->supplier_phone,
                    'alamat'  => $this->supplier_address,
                ]);
                $final_supplier_id = $newSupplier->id;
            }

            $paths = [];
            foreach ($this->foto_nota as $foto) {
                $paths[] = $foto->store('pembelian', 'public');
            }

            $this->status = match (true) {
                $grand > 0 && $dibayar >= $grand  => ModelsPembelian::STATUS_LUNAS,
                $dibayar > 0 && $dibayar < $grand => ModelsPembelian::STATUS_CICILAN,
                default                            => ModelsPembelian::STATUS_HUTANG,
            };

            $pembelian = ModelsPembelian::create([
                'nomor_nota'       => $this->nomor_nota,
                'created_by'       => $this->created_by,
                'tanggal'          => $this->tanggal,
                'supplier_id'      => $final_supplier_id,
                'supplier_name'    => $this->supplier_name,
                'supplier_phone'   => $this->supplier_phone,
                'supplier_address' => $this->supplier_address,
                'status'           => $this->status,
                'catatan'          => $this->catatan,
                'foto'             => !empty($paths) ? $paths : null,
                'sub_total'        => $this->sub_total,
                'total_diskon'     => $this->parseNumber($this->total_diskon),
                'total_ppn'        => $this->parseNumber($this->total_ppn),
                'ongkir'           => $this->parseNumber($this->ongkir),
                'biaya_lain'       => $this->parseNumber($this->biaya_lain),
                'grand_total'      => $grand,
            ]);

            $detailData = [];
            foreach ($this->items as $item) {
                if (empty($item['barang_id'])) continue;
                
                $detailData[] = [
                    'pembelian_id' => $pembelian->id,
                    'barang_id'    => $item['barang_id'],
                    'kode_barang'  => $item['kode_barang'],
                    'nama_barang'  => $item['nama_barang'],
                    'satuan'       => $item['satuan'],
                    'qty'          => $this->parseNumber($item['qty']),
                    'kubikasi'     => $this->parseNumber($item['kubikasi']), // Insert kubikasi
                    'hitung_dari'  => $item['hitung_dari'] ?? 'qty',         // Insert dasar perhitungan
                    'harga_beli'   => $this->parseNumber($item['harga_beli']),
                    'diskon'       => $this->parseNumber($item['diskon']),
                    'subtotal'     => $this->parseNumber($item['subtotal']),
                    'catatan'      => $item['catatan'] ?? '',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }

            if (!empty($detailData)) {
                DetailPembelian::insert($detailData);
            }

            if ($dibayar > 0) {
                PembelianMetodePembayaran::create([
                    'pembelian_id'     => $pembelian->id,
                    'created_by'       => $this->created_by,
                    'tanggal_bayar'    => $this->tanggal_bayar,
                    'amount'           => $dibayar,
                    'payment_method'   => $this->payment_method,
                    'reference_number' => $this->payment_reference,
                    'catatan'          => $this->payment_catatan,
                ]);
            }

            DB::commit();

            Notification::make()
                ->title('Transaksi Berhasil!')
                ->body('Data pembelian dan pembayaran telah tercatat.')
                ->success()
                ->send();

            $this->dispatch('clearLocalStorage');

            $this->redirect(PembeliansResource::getUrl('index'));
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->title('Gagal Menyimpan')
                ->body('Kesalahan: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    #[\Livewire\Attributes\On('restoreState')]
    public function restoreState(array $state): void
    {
        $this->items             = $state['items'] ?? [];
        $this->nomor_nota        = $state['nomor_nota'] ?? null;
        $this->tanggal           = $state['tanggal'] ?? now()->format('Y-m-d');
        $this->supplier_id       = $state['supplier_id'] ?? '';
        $this->supplier_name     = $state['supplier_name'] ?? null;
        $this->supplier_phone    = $state['supplier_phone'] ?? null;
        $this->supplier_address  = $state['supplier_address'] ?? null;
        $this->is_new_supplier   = (bool) ($state['is_new_supplier'] ?? false);
        $this->catatan           = $state['catatan'] ?? null;
        $this->sub_total         = $state['sub_total'] ?? 0;
        $this->total_diskon      = $state['total_diskon'] ?? null;
        $this->total_ppn         = $state['total_ppn'] ?? null;
        $this->ongkir            = $state['ongkir'] ?? null;
        $this->biaya_lain        = $state['biaya_lain'] ?? null;
        $this->payment_method    = $state['payment_method'] ?? PembelianMetodePembayaran::METODE_TUNAI;
        $this->payment_amount    = $state['payment_amount'] ?? null;
        $this->tanggal_bayar     = $state['tanggal_bayar'] ?? now()->format('Y-m-d');
        $this->payment_reference = $state['payment_reference'] ?? '';
        $this->payment_catatan   = $state['payment_catatan'] ?? '';

        $this->recalculateSubTotal();
    }
}