<?php

namespace App\Filament\Resources\ReturnPenjualans\Pages;

use App\Filament\Resources\ReturnPenjualans\ReturnPenjualanResource;
use App\Models\Penjualan;
use App\Models\ReturnPenjualan;
use App\Models\ReturnPenjualanDetail;
use App\Services\JurnalReturnPenjualanService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class FormReturnPenjualan extends Page
{
    protected static string $resource = ReturnPenjualanResource::class;

    protected string $view = 'filament.resources.return-penjualans.pages.form-return-penjualan';

    protected static ?string $title = 'Form Retur Penjualan';

    // ── STATE FORM ──────────────────────────────────────────────────────────

    /**
     * Kata kunci pencarian nota. Disimpan di query string (?cari=...) supaya
     * hasil pencarian tetap sama walau halaman di-refresh.
     */
    #[Url(as: 'cari', keep: false)]
    public string $searchNota = '';

    /**
     * ID Penjualan yang sedang diproses retur. Disimpan di query string
     * (?nota=ID) agar link bisa dibagikan/dibookmark dan tetap berada di
     * Step 2 (pilih barang) walau halaman direfresh.
     */
    #[Url(as: 'nota', keep: false)]
    public ?int $selectedPenjualanId = null;

    public ?Penjualan $selectedNota = null;

    /**
     * Item barang dari nota terpilih yang siap diretur.
     * array<int, array{
     *    detail_id: int,
     *    barang_id: int,
     *    nama_barang: string,
     *    satuan: string,
     *    harga_jual: float,
     *    harga_beli: float,
     *    qty_beli: float,
     *    qty_teretur: float,
     *    sisa_qty: float,
     *    qty_retur: float,
     *    potongan: float,
     *    subtotal: float,
     *    keterangan_item: string,
     *    selected: bool
     * }>
     */
    public array $items = [];

    // Pilihan akun pengembalian (default Kas Bu Mut 1101.1)
    public string $akun_pengembalian = '1101.1';

    public string $keterangan_retur = '';

    public string $tanggal = '';

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d\TH:i');

        // Rehidrasi state dari URL (?nota=ID). Jika ada dan valid, langsung
        // bawa user ke Step 2 dengan data nota yang sama seperti sebelum refresh.
        if ($this->selectedPenjualanId) {
            $this->loadNota($this->selectedPenjualanId, silent: true);
        }
    }

    /**
     * Daftar nota penjualan yang valid untuk diretur (hanya yang sudah LUNAS dan tervalidasi).
     */
    #[Computed]
    public function notaResults(): Collection
    {
        $notas = Penjualan::query()
            ->whereNotNull('validated_by')
            ->where('status_transaksi', 'LUNAS')
            ->with(['details'])
            ->when($this->searchNota, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('no_nota', 'like', "%{$this->searchNota}%")
                        ->orWhere('nama_customer', 'like', "%{$this->searchNota}%");
                });
            })
            ->orderByDesc('tanggal')
            ->limit(20)
            ->get();

        if ($notas->isEmpty()) {
            return collect();
        }

        $noNotas = $notas->pluck('no_nota')->toArray();

        // Ambil akumulasi retur per no_nota
        $returTotals = DB::table('penjualan_return')
            ->join('penjualan_return_detail', 'penjualan_return.id', '=', 'penjualan_return_detail.id_return')
            ->whereIn('penjualan_return.no_nota', $noNotas)
            ->whereIn('penjualan_return.status_return', ['DIPROSES', 'DITERIMA', 'SELESAI'])
            ->groupBy('penjualan_return.no_nota')
            ->select('penjualan_return.no_nota', DB::raw('SUM(penjualan_return_detail.qty) as total_qty_retur'))
            ->pluck('total_qty_retur', 'no_nota')
            ->toArray();

        foreach ($notas as $nota) {
            $totalQtyBeli = (float) $nota->details->sum('qty');
            $totalQtyRetur = (float) ($returTotals[$nota->no_nota] ?? 0);
            $nota->total_qty_beli = $totalQtyBeli;
            $nota->total_qty_retur = $totalQtyRetur;
            $nota->sisa_qty_retur = max(0, $totalQtyBeli - $totalQtyRetur);
            $nota->is_retur_habis = ($nota->sisa_qty_retur <= 0 && $totalQtyBeli > 0);
            $nota->pernah_diretur = ($totalQtyRetur > 0 && ! $nota->is_retur_habis);
        }

        return $notas;
    }

    /**
     * Dipanggil dari UI ketika user mengetuk sebuah nota di Step 1.
     */
    public function pilihNota(int $id): void
    {
        $this->loadNota($id, silent: false);
    }

    /**
     * Ambil data nota + detail item, dan siapkan state $items untuk Step 2.
     * Dipakai baik dari klik user (pilihNota) maupun dari rehidrasi URL saat mount().
     *
     * @param  bool  $silent  Jika true, notifikasi kegagalan tidak "berisik" (mis. saat refresh
     *                        halaman dan nota sudah tidak valid lagi) — cukup kembali ke Step 1.
     */
    private function loadNota(int $id, bool $silent = false): void
    {
        $nota = Penjualan::with(['details.barang'])->find($id);

        if (! $nota) {
            if (! $silent) {
                Notification::make()->title('Nota tidak ditemukan.')->danger()->send();
            }
            $this->resetSelection();

            return;
        }

        if ($nota->status_transaksi !== 'LUNAS') {
            if (! $silent) {
                Notification::make()
                    ->title('Nota Belum Lunas')
                    ->body('Hanya nota penjualan dengan status LUNAS yang dapat diretur.')
                    ->danger()
                    ->send();
            }
            $this->resetSelection();

            return;
        }

        // Ambil histori retur sebelumnya untuk nota ini
        $historiRetur = DB::table('penjualan_return')
            ->join('penjualan_return_detail', 'penjualan_return.id', '=', 'penjualan_return_detail.id_return')
            ->where('penjualan_return.no_nota', $nota->no_nota)
            ->whereIn('penjualan_return.status_return', ['DIPROSES', 'DITERIMA', 'SELESAI'])
            ->select('penjualan_return_detail.id_barang', DB::raw('SUM(penjualan_return_detail.qty) as total_retur'))
            ->groupBy('penjualan_return_detail.id_barang')
            ->pluck('total_retur', 'id_barang')
            ->toArray();

        $totalQtyBeli = (float) $nota->details->sum('qty');
        $totalQtyTeretur = (float) array_sum($historiRetur);
        if ($totalQtyBeli > 0 && $totalQtyTeretur >= $totalQtyBeli) {
            if (! $silent) {
                Notification::make()
                    ->title('Semua Barang Sudah Diretur')
                    ->body("Semua barang pada nota {$nota->no_nota} sudah diretur seluruhnya ({$totalQtyTeretur} unit). Tidak dapat diretur lagi.")
                    ->warning()
                    ->send();
            }
            $this->resetSelection();

            return;
        }

        $this->selectedPenjualanId = $nota->id;
        $this->selectedNota = $nota;
        $this->items = [];

        foreach ($nota->details as $d) {
            $qtyBeli = (float) $d->qty;
            $qtyTeretur = (float) ($historiRetur[$d->barang_id] ?? 0);
            $sisaQty = max(0, $qtyBeli - $qtyTeretur);
            $hargaBeli = (float) ($d->barang->harga_beli ?? 0);
            $hargaJual = (float) $d->harga_jual;

            $this->items[] = [
                'detail_id' => $d->id,
                'barang_id' => $d->barang_id,
                'nama_barang' => $d->barang->nama_barang ?? ($d->nama_barang ?? 'Barang'),
                'satuan' => $d->satuan ?? 'unit',
                'harga_jual' => $hargaJual,
                'harga_beli' => $hargaBeli,
                'qty_beli' => $qtyBeli,
                'qty_teretur' => $qtyTeretur,
                'sisa_qty' => $sisaQty,
                'qty_retur' => 0,
                'potongan' => 0,
                'subtotal' => 0,
                'keterangan_item' => '',
                'selected' => false,
            ];
        }

        // Default: jika nota asal transfer dan ada rekening, sesuaikan default akun pengembalian
        if ($nota->metode_pembayaran === 'TRANSFER' && $nota->rekeningPerusahaan?->subAnakAkun) {
            $kodeAkunRek = $nota->rekeningPerusahaan->subAnakAkun->kode_sub_anak_akun;
            if (array_key_exists($kodeAkunRek, JurnalReturnPenjualanService::AKUN_REFUND)) {
                $this->akun_pengembalian = $kodeAkunRek;
            }
        }
    }

    /**
     * Batalkan pilihan nota (dipanggil dari tombol "Ganti Transaksi" di UI).
     */
    public function batalPilihNota(): void
    {
        $this->resetSelection();
    }

    /**
     * Reset seluruh state Step 2 kembali ke Step 1, termasuk menghapus
     * parameter ?nota= dari URL.
     */
    private function resetSelection(): void
    {
        $this->selectedPenjualanId = null;
        $this->selectedNota = null;
        $this->items = [];
        $this->akun_pengembalian = '1101.1';
        $this->keterangan_retur = '';
    }

    /**
     * Tombol cepat: Retur Semua atau Kosongkan Semua.
     */
    public function toggleReturSemua(bool $setSemua = true): void
    {
        foreach ($this->items as $idx => $item) {
            if ($setSemua) {
                if (($item['sisa_qty'] ?? 0) > 0) {
                    $this->items[$idx]['selected'] = true;
                    $this->items[$idx]['qty_retur'] = $item['sisa_qty'];
                    $this->items[$idx]['subtotal'] = $item['sisa_qty'] * $item['harga_jual'];
                } else {
                    $this->items[$idx]['selected'] = false;
                    $this->items[$idx]['qty_retur'] = 0;
                    $this->items[$idx]['subtotal'] = 0;
                }
            } else {
                $this->items[$idx]['selected'] = false;
                $this->items[$idx]['qty_retur'] = 0;
                $this->items[$idx]['subtotal'] = 0;
            }
        }
    }

    /**
     * Cek apakah masih ada barang dalam nota yang memiliki sisa untuk diretur.
     */
    #[Computed]
    public function hasSisaBarang(): bool
    {
        return collect($this->items)->contains(fn ($it) => ($it['sisa_qty'] ?? 0) > 0);
    }

    /**
     * Toggle status pilihan item.
     */
    public function toggleItem(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        // Jangan ubah status barang yang sisa_qty-nya sudah habis
        if (($this->items[$index]['sisa_qty'] ?? 0) <= 0) {
            $this->items[$index]['selected'] = false;
            $this->items[$index]['qty_retur'] = 0;

            return;
        }

        $current = $this->items[$index]['selected'];
        $this->items[$index]['selected'] = ! $current;

        if ($this->items[$index]['selected']) {
            if ($this->items[$index]['qty_retur'] <= 0 && $this->items[$index]['sisa_qty'] > 0) {
                $this->items[$index]['qty_retur'] = $this->items[$index]['sisa_qty'];
            }
        } else {
            $this->items[$index]['qty_retur'] = 0;
        }

        $this->recalculateItemSubtotal($index);
    }

    /**
     * Update qty item retur.
     */
    public function updatedItems($value, $key): void
    {
        // Format key: "0.qty_retur"
        $parts = explode('.', $key);
        if (count($parts) === 2 && $parts[1] === 'qty_retur') {
            $idx = (int) $parts[0];
            $this->validateAndSyncItemQty($idx);
        }
    }

    private function validateAndSyncItemQty(int $idx): void
    {
        if (! isset($this->items[$idx])) {
            return;
        }

        $sisa = (float) ($this->items[$idx]['sisa_qty'] ?? 0);
        if ($sisa <= 0) {
            $this->items[$idx]['qty_retur'] = 0;
            $this->items[$idx]['selected'] = false;
            $this->items[$idx]['subtotal'] = 0;

            return;
        }

        $qty = (float) ($this->items[$idx]['qty_retur'] ?? 0);

        if ($qty > $sisa) {
            $qty = $sisa;
            $this->items[$idx]['qty_retur'] = $qty;
            Notification::make()
                ->title('Maksimal Qty')
                ->body("Qty retur tidak boleh melebihi sisa yang tersedia ({$sisa}).")
                ->warning()
                ->send();
        }

        if ($qty < 0) {
            $qty = 0;
            $this->items[$idx]['qty_retur'] = 0;
        }

        $this->items[$idx]['selected'] = ($qty > 0);
        $this->recalculateItemSubtotal($idx);
    }

    private function recalculateItemSubtotal(int $idx): void
    {
        if (isset($this->items[$idx])) {
            $qty = (float) ($this->items[$idx]['qty_retur'] ?? 0);
            $harga = (float) ($this->items[$idx]['harga_jual'] ?? 0);
            $potongan = (float) ($this->items[$idx]['potongan'] ?? 0);
            $this->items[$idx]['subtotal'] = max(0, ($qty * $harga) - $potongan);
        }
    }

    /**
     * Kalkulasi live rincian retur & Buku Kitab.
     */
    #[Computed]
    public function kalkulasi(): array
    {
        if (! $this->selectedNota) {
            return [
                'is_dp' => false,
                'is_retur_penuh' => false,
                'is_ppn' => false,
                'jenis_retur' => 'NORMAL',
                'kode_kitab' => 'retur_normal_kas_bu_mut',
                'nama_kitab' => 'RETUR NORMAL (KAS BU MUT)',
                'akun_pengembalian' => '1101.1',
                'subtotal_retur' => 0,
                'ppn_nominal' => 0,
                'total_retur' => 0,
                'total_hpp' => 0,
                'nomor_kitab' => 1,
            ];
        }

        $selectedItems = collect($this->items)
            ->filter(fn ($it) => ($it['selected'] ?? false) && ($it['qty_retur'] ?? 0) > 0)
            ->map(fn ($it) => [
                'id_barang' => $it['barang_id'],
                'nama_barang' => $it['nama_barang'],
                'qty' => (float) $it['qty_retur'],
                'harga_jual' => (float) $it['harga_jual'],
                'harga_beli' => (float) $it['harga_beli'],
                'potongan' => (float) ($it['potongan'] ?? 0),
            ])
            ->values()
            ->all();

        $calc = app(JurnalReturnPenjualanService::class)->kalkulasi(
            $this->selectedNota,
            $selectedItems,
            $this->akun_pengembalian
        );

        $calc['nomor_kitab'] = $this->getNomorKitab($calc['kode_kitab']);

        return $calc;
    }

    /**
     * Nomor urut 1 - 16 sesuai daftar resmi Buku Kitab Retur Normal (8 PPN & 8 Non-PPN).
     */
    private function getNomorKitab(string $kodeKitab): int
    {
        $map = [
            // RETUR NORMAL — PPN (1 - 8)
            'retur_normal_kas_bu_mut' => 1,
            'retur_normal_bank_99' => 2,
            'retur_normal_bank_wahana' => 3,
            'retur_normal_bank_wpi' => 4,
            'retur_normal_bank_industri' => 5,
            'retur_normal_bank_intan' => 6,
            'retur_normal_bank_bu_eddy' => 7,
            'retur_normal_liabilitas_jk_pendek' => 8,

            // RETUR NORMAL — NON PPN (9 - 16)
            'retur_normal_non_ppn_kas_bu_mut' => 9,
            'retur_normal_non_ppn_bank_99' => 10,
            'retur_normal_non_ppn_bank_wahana' => 11,
            'retur_normal_non_ppn_bank_wpi' => 12,
            'retur_normal_non_ppn_bank_industri' => 13,
            'retur_normal_non_ppn_bank_intan' => 14,
            'retur_normal_non_ppn_bank_bu_eddy' => 15,
            'retur_normal_non_ppn_liabilitas_jk_pendek' => 16,
        ];

        return $map[$kodeKitab] ?? 0;
    }

    /**
     * Simpan transaksi retur penjualan.
     */
    public function simpanRetur()
    {
        if (! $this->selectedNota) {
            Notification::make()->title('Silakan pilih nota penjualan terlebih dahulu.')->danger()->send();

            return;
        }

        if ($this->selectedNota->status_transaksi !== 'LUNAS') {
            Notification::make()
                ->title('Nota Belum Lunas')
                ->body('Hanya nota penjualan dengan status LUNAS yang dapat diretur.')
                ->danger()
                ->send();

            return;
        }

        $itemsRetur = collect($this->items)->filter(fn ($it) => ($it['selected'] ?? false) && ($it['qty_retur'] ?? 0) > 0);

        if ($itemsRetur->isEmpty()) {
            Notification::make()
                ->title('Pilih Barang')
                ->body('Pilih minimal 1 barang dengan jumlah retur lebih dari 0.')
                ->warning()
                ->send();

            return;
        }

        $this->validate([
            'keterangan_retur' => ['required', 'string', 'min:3'],
        ], [
            'keterangan_retur.required' => 'Alasan retur wajib diisi.',
            'keterangan_retur.min' => 'Alasan retur terlalu singkat, mohon jelaskan lebih detail.',
        ]);

        $calc = $this->kalkulasi;

        try {
            $returnHeader = null;

            DB::transaction(function () use ($itemsRetur, $calc, &$returnHeader) {
                // Generate nomor retur unik
                $todayPrefix = 'RET-'.date('Ymd');
                $lastCount = ReturnPenjualan::where('no_retur', 'like', "{$todayPrefix}%")->count() + 1;
                $noRetur = sprintf('%s-%04d', $todayPrefix, $lastCount);

                $refundConfig = JurnalReturnPenjualanService::AKUN_REFUND[$this->akun_pengembalian] ?? [
                    'metode' => 'TUNAI',
                    'nama' => 'KAS BU MUT',
                ];

                $metode = $refundConfig['metode'];
                $userId = auth()->id() ?: 1;

                $returnHeader = ReturnPenjualan::create([
                    'penjualan_id' => $this->selectedNota->id,
                    'no_retur' => $noRetur,
                    'no_nota' => $this->selectedNota->no_nota,
                    'tanggal' => $this->tanggal ? date('Y-m-d H:i:s', strtotime($this->tanggal)) : now(),
                    'nama_customer' => $this->selectedNota->nama_customer ?: 'Pelanggan',
                    'is_member' => (bool) $this->selectedNota->is_member,
                    'alamat' => $this->selectedNota->alamat,
                    'metode_pembayaran' => $metode,
                    'bank' => $refundConfig['nama'],
                    'no_rekening' => $this->akun_pengembalian,
                    'kendaraan' => $this->selectedNota->kendaraan,
                    'plat_kendaraan' => $this->selectedNota->plat_kendaraan,
                    'nama_sopir' => $this->selectedNota->nama_sopir,
                    'sub_total' => $calc['subtotal_retur'],
                    'ppn_nominal' => $calc['ppn_nominal'],
                    'total' => $calc['total_retur'],
                    'bayar' => $calc['total_retur'],
                    'kembalian' => 0,
                    'keterangan' => $this->keterangan_retur ?: "Retur Penjualan Ref: {$this->selectedNota->no_nota}",
                    'status_return' => 'DITERIMA',
                    'jenis_retur' => $calc['jenis_retur'],
                    'kode_kitab' => $calc['kode_kitab'],
                    'akun_pengembalian' => $this->akun_pengembalian,
                    'created_by' => $userId,
                    'validate_by' => $userId,
                    'toko_id' => $this->selectedNota->toko_id,
                ]);

                foreach ($itemsRetur as $item) {
                    ReturnPenjualanDetail::create([
                        'id_return' => $returnHeader->id,
                        'id_barang' => $item['barang_id'],
                        'penjualan_detail_id' => $item['detail_id'],
                        'nama_barang' => $item['nama_barang'],
                        'satuan' => $item['satuan'],
                        'harga_awal' => $item['harga_jual'],
                        'harga_beli' => $item['harga_beli'],
                        'harga_jual' => $item['harga_jual'],
                        'potongan' => $item['potongan'] ?? 0,
                        'qty' => $item['qty_retur'],
                        'subtotal' => $item['subtotal'],
                        'keterangan' => $item['keterangan_item'] ?: $this->keterangan_retur,
                    ]);
                }

                // Masuk ke Jurnal Pembantu Header secara otomatis
                app(JurnalReturnPenjualanService::class)->buatJurnalReturn($returnHeader, (int) $userId);
            });

            $noJurnal = $returnHeader?->no_jurnal;
            $infoJurnal = $noJurnal ? " dan masuk ke Jurnal Pembantu Header (No. Jurnal #{$noJurnal})" : '';

            Notification::make()
                ->title('Retur Berhasil Disimpan & Masuk Jurnal')
                ->body("Retur {$returnHeader->no_retur} untuk nota {$this->selectedNota->no_nota} berhasil dicatat{$infoJurnal} dengan template: [{$calc['nomor_kitab']}] {$calc['nama_kitab']}.")
                ->success()
                ->send();

            return redirect()->to(ReturnPenjualanResource::getUrl('index'));

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menyimpan Retur')
                ->body('Terjadi kesalahan: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }
}
