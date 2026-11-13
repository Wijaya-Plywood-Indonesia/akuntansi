<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrdersResource;
use App\Models\Barang;
use App\Models\PurchaseOrder as ModelPurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;

class FormPurchaseOrder extends Page
{

    protected static string $resource = PurchaseOrdersResource::class;

    protected string $view = 'filament.resources.purchase-orders.pages.form-purchase-order';

    // Record jika mode edit
    public ?int $recordId = null;
    public ?ModelPurchaseOrder $record = null;
    public bool $isEdit = false;

    // Header PO
    public $nomor_po;
    public $created_by;
    public $created_by_name;
    public $tanggal;
    public $tanggal_jatuh_tempo;

    // Supplier
    public $supplier_id        = '';
    public $supplier_name;
    public $supplier_phone;
    public $supplier_address;
    public $supplier_bank;
    public $supplier_no_rek;
    public $supplier_atas_nama;
    public $is_new_supplier     = false;

    // Notes & files
    public $catatan;
    public $status  = ModelPurchaseOrder::STATUS_DRAFT;

    // Daftar file output PO yang ada di C:/data/upload/PO/
    public array $output_files = [];

    // Items
    public $items = [];

    // Search
    public string $search        = '';
    public array  $searchResults = [];
    public bool   $showDropdown  = false;

    // Totals
    public $sub_total    = 0;
    public $total_diskon = null;
    public $ppn_persen   = 0;
    public $total_ppn    = null;
    public $ongkir       = null;
    public $biaya_lain   = null;

    public function getTitle(): string
    {
        return $this->isEdit
            ? 'Edit Purchase Order (PO) #' . ($this->record->nomor_po ?? '')
            : 'Buat Purchase Order (PO)';
    }

    public function mount(mixed $record = null): void
    {
        if ($record) {
            $this->isEdit = true;
            if ($record instanceof ModelPurchaseOrder) {
                $this->record = $record->loadMissing(['items', 'createdBy']);
                $this->recordId = $record->id;
            } else {
                $this->recordId = (int) $record;
                $this->record = ModelPurchaseOrder::with(['items', 'createdBy'])->findOrFail($record);
            }

            $this->nomor_po           = $this->record->nomor_po;
            $this->created_by         = $this->record->created_by;
            $this->created_by_name    = $this->record->createdBy->name ?? 'User';
            $this->tanggal            = $this->record->tanggal ? $this->record->tanggal->format('Y-m-d') : now()->format('Y-m-d');
            $this->tanggal_jatuh_tempo = $this->record->tanggal_jatuh_tempo ? $this->record->tanggal_jatuh_tempo->format('Y-m-d') : null;

            $this->supplier_id        = $this->record->supplier_id ?? '';
            $this->supplier_name      = $this->record->supplier_name;
            $this->supplier_phone     = $this->record->supplier_phone;
            $this->supplier_address   = $this->record->supplier_address;
            $this->supplier_bank      = $this->record->supplier_bank;
            $this->supplier_no_rek    = $this->record->supplier_no_rek;
            $this->supplier_atas_nama = $this->record->supplier_atas_nama;
            $this->status             = $this->record->status;
            $this->catatan            = $this->record->catatan;

            // Ambil daftar file output PO yang tersimpan di folder C:/data/upload/PO/
            // yang terkait dengan PO ini (berdasarkan kolom foto)
            $savedFiles = is_array($this->record->foto) ? $this->record->foto : [];
            $this->output_files = $savedFiles;

            $this->total_diskon       = $this->record->total_diskon > 0 ? (float) $this->record->total_diskon : null;
            $this->ongkir             = $this->record->ongkir > 0 ? (float) $this->record->ongkir : null;
            $this->biaya_lain         = $this->record->biaya_lain > 0 ? (float) $this->record->biaya_lain : null;
            $this->total_ppn          = $this->record->total_ppn > 0 ? (float) $this->record->total_ppn : null;

            // Load items
            $this->items = [];
            foreach ($this->record->items as $item) {
                $this->items[] = [
                    'barang_id'   => $item->barang_id,
                    'kode_barang' => $item->kode_barang,
                    'nama_barang' => $item->nama_barang,
                    'satuan'      => $item->satuan,
                    'qty'         => (float) $item->qty,
                    'kubikasi'    => (float) $item->kubikasi,
                    'hitung_dari' => $item->hitung_dari ?? 'qty',
                    'harga_beli'  => (float) $item->harga_beli,
                    'diskon'      => (float) $item->diskon,
                    'subtotal'    => (float) $item->subtotal,
                    'catatan'     => $item->catatan ?? '',
                ];
            }

            $this->recalculateSubTotal();

            // Hitung persentase PPN jika ada total_ppn tersimpan
            if ($this->sub_total > 0 && (float) $this->record->total_ppn > 0) {
                $this->ppn_persen = round(((float) $this->record->total_ppn / $this->sub_total) * 100, 2);
            }
        } else {
            $this->isEdit = false;
            $this->created_by      = auth()->id();
            $this->created_by_name = auth()->user()->name ?? 'User';
            $this->tanggal         = now()->format('Y-m-d');
            $this->nomor_po        = 'PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        }

        // Load semua file di folder output PO
        $this->loadOutputFiles();
    }

    public function loadOutputFiles(): void
    {
        $dir = 'C:/data/upload/PO';
        if (!is_dir($dir)) {
            $this->output_files = [];
            return;
        }
        $files = scandir($dir);
        $result = [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $fullPath = $dir . '/' . $file;
            if (is_file($fullPath)) {
                $result[] = [
                    'name' => $file,
                    'size' => filesize($fullPath),
                    'modified' => filemtime($fullPath),
                ];
            }
        }
        // Urutkan berdasarkan waktu modifikasi terbaru
        usort($result, fn($a, $b) => $b['modified'] - $a['modified']);
        $this->output_files = $result;
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
                $qty      = floatval($this->items[$index]['qty'] ?? 0) + 1;
                $kubikasi = floatval($this->items[$index]['kubikasi'] ?? 0);
                $hargaItem = floatval($this->items[$index]['harga_beli'] ?? 0);
                $diskon    = floatval($this->items[$index]['diskon'] ?? 0);
                $hitungDari = $this->items[$index]['hitung_dari'] ?? 'qty';

                $subtotalLama = floatval($this->items[$index]['subtotal'] ?? 0);
                $pengali      = ($hitungDari === 'm3') ? $kubikasi : $qty;
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
            'hitung_dari' => 'qty',
            'harga_beli'  => $harga,
            'diskon'      => 0,
            'subtotal'    => $subtotal,
            'catatan'     => '',
        ];

        $this->sub_total   += $subtotal;
        $this->search       = '';
        $this->showDropdown = false;
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

    public function updatedPpnPersen(): void
    {
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
            $pengali     = ($hitung_dari === 'm3') ? $kubikasi : $qty;
            $subtotal    = max(0.0, ($pengali * $harga) - $diskon);
            $this->items[$index]['subtotal'] = $subtotal;
            $total += $subtotal;
        }
        $this->sub_total  = $total;
        $this->total_ppn  = round(($this->sub_total * ($this->parseNumber($this->ppn_persen))) / 100);
    }

    #[Computed]
    public function grandTotal(): float
    {
        return (new ModelPurchaseOrder)->hitungGrandTotal(
            subTotal:    (float) $this->sub_total,
            totalDiskon: $this->parseNumber($this->total_diskon),
            totalPpn:    $this->parseNumber($this->total_ppn),
            ongkir:      $this->parseNumber($this->ongkir),
            biayaLain:   $this->parseNumber($this->biaya_lain),
        );
    }

    public function getGrandTotalProperty(): float
    {
        return $this->grandTotal();
    }

    private function parseNumber(mixed $value): float
    {
        if (is_null($value) || $value === '') return 0.0;
        if (is_numeric($value)) return (float) $value;

        $str       = (string) $value;
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

        $uniqueRule = $this->isEdit
            ? 'required|unique:purchase_orders,nomor_po,' . $this->recordId
            : 'required|unique:purchase_orders,nomor_po';

        try {
            $this->validate([
                'nomor_po'          => $uniqueRule,
                'tanggal'           => 'required|date',
                'supplier_id'       => 'required_unless:is_new_supplier,true',
                'supplier_name'     => 'required_if:is_new_supplier,true',
                'items'             => 'required|array|min:1',
                'items.*.barang_id' => 'required',
                'items.*.qty'       => 'required|numeric|min:0.01',
                'items.*.harga_beli'=> 'required|numeric|min:0',
            ], [
                'nomor_po.required'           => 'Nomor PO wajib diisi.',
                'nomor_po.unique'             => 'Nomor PO ini sudah terdaftar.',
                'tanggal.required'            => 'Tanggal PO wajib diisi.',
                'supplier_id.required_unless' => 'Silakan pilih supplier atau tambah supplier baru.',
                'supplier_name.required_if'   => 'Nama supplier baru wajib diisi.',
                'items.required'              => 'Minimal harus ada 1 item barang.',
                'items.min'                   => 'Minimal harus ada 1 item barang.',
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

            $grand = $this->grandTotal();

            $poData = [
                'nomor_po'            => $this->nomor_po,
                'created_by'          => $this->created_by,
                'tanggal'             => $this->tanggal,
                'tanggal_jatuh_tempo' => $this->tanggal_jatuh_tempo ?: null,
                'supplier_id'         => $final_supplier_id,
                'supplier_name'       => $this->supplier_name,
                'supplier_phone'      => $this->supplier_phone,
                'supplier_address'    => $this->supplier_address,
                'status'              => $this->status,
                'catatan'             => $this->catatan,
                'foto'                => null,
                'sub_total'           => $this->sub_total,
                'total_diskon'        => $this->parseNumber($this->total_diskon),
                'total_ppn'           => $this->parseNumber($this->total_ppn),
                'ongkir'              => $this->parseNumber($this->ongkir),
                'biaya_lain'          => $this->parseNumber($this->biaya_lain),
                'grand_total'         => $grand,
            ];

            if ($this->isEdit) {
                $po = $this->record;
                $po->update($poData);
                // Hapus item lama dan insert yang baru
                $po->items()->delete();
            } else {
                $po = ModelPurchaseOrder::create($poData);
            }

            $itemData = [];
            foreach ($this->items as $item) {
                if (empty($item['barang_id'])) continue;
                $itemData[] = [
                    'purchase_order_id' => $po->id,
                    'barang_id'         => $item['barang_id'],
                    'kode_barang'       => $item['kode_barang'],
                    'nama_barang'       => $item['nama_barang'],
                    'satuan'            => $item['satuan'],
                    'qty'               => $this->parseNumber($item['qty']),
                    'kubikasi'          => $this->parseNumber($item['kubikasi']),
                    'hitung_dari'       => $item['hitung_dari'] ?? 'qty',
                    'harga_beli'        => $this->parseNumber($item['harga_beli']),
                    'diskon'            => $this->parseNumber($item['diskon']),
                    'subtotal'          => $this->parseNumber($item['subtotal']),
                    'catatan'           => $item['catatan'] ?? '',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }

            if (!empty($itemData)) {
                PurchaseOrderItem::insert($itemData);
            }

            DB::commit();

            Notification::make()
                ->title($this->isEdit ? 'PO Berhasil Diperbarui!' : 'PO Berhasil Disimpan!')
                ->body("Purchase Order {$this->nomor_po} telah berhasil " . ($this->isEdit ? 'diperbarui.' : 'dicatat.'))
                ->success()
                ->send();

            $this->redirect(PurchaseOrdersResource::getUrl('index'));

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->title('Gagal Menyimpan')
                ->body('Kesalahan: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
