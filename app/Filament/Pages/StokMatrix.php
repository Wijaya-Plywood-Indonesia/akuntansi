<?php

namespace App\Filament\Pages;

use App\Models\Barang;
use App\Models\JurnalUmum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class StokMatrix extends Page
{
    protected string $view = 'filament.pages.stok-matrix';

    protected static string|UnitEnum|null $navigationGroup = 'Stock Barang';

    public static ?string $navigationLabel = 'Matrix Barang';

    protected $barangs;

    protected $stok;

    protected int $pairs = 5;

    public function mount(): void
    {
        // Ambil semua barang yang punya kode_sub_anak_akun valid
        $this->barangs = Barang::with(['subAnakAkun', 'satuan', 'kategori'])
            ->whereHas('subAnakAkun', function ($query) {
                $query->whereNotNull('kode_sub_anak_akun')
                    ->where('kode_sub_anak_akun', '!=', '');
            })
            ->orderBy('nama_barang')
            ->get();

        $barangIds = $this->barangs->pluck('id')->toArray();

        // Ambil transaksi jurnal umum yang punya id_barang valid (otomatis exclude yang NULL)
        $transaksisGrouped = JurnalUmum::select(
            'id_barang',
            'map',
            DB::raw('SUM(COALESCE(banyak, 0)) as total_qty'),
            DB::raw('SUM(COALESCE(m3, 0)) as total_m3')
        )
            ->whereIn('id_barang', $barangIds)
            ->groupBy('id_barang', 'map')
            ->get()
            ->groupBy('id_barang');

        $matrixTemporaryStok = [];

        foreach ($this->barangs as $barang) {
            $totalQty = 0.0;
            $totalM3 = 0.0;

            if (isset($transaksisGrouped[$barang->id])) {
                foreach ($transaksisGrouped[$barang->id] as $trx) {
                    $isDebit = in_array(strtolower($trx->map), ['d', 'debit']);
                    $qty = (float) $trx->total_qty;
                    $m3 = (float) $trx->total_m3;

                    if ($isDebit) {
                        $totalQty += $qty;
                        $totalM3 += $m3;
                    } else {
                        $totalQty -= $qty;
                        $totalM3 -= $m3;
                    }
                }
            }

            $matrixTemporaryStok[$barang->id] = (object) [
                'stok' => $totalQty,
                'm3' => $totalM3,
            ];
        }

        $this->stok = collect($matrixTemporaryStok);
    }

    protected function getViewData(): array
    {
        $totalBarang = $this->barangs->count();
        $totalStokAktifCount = 0;
        $totalStokKosongCount = 0;
        $totalAkumulasiStok = 0.0;
        $totalAkumulasiM3 = 0.0;

        foreach ($this->barangs as $barang) {
            $qty = $this->stok[$barang->id]->stok ?? 0.0;
            $m3 = $this->stok[$barang->id]->m3 ?? 0.0;
            if ($qty > 0 || $m3 > 0) {
                $totalStokAktifCount++;
                $totalAkumulasiStok += $qty;
                $totalAkumulasiM3 += $m3;
            } else {
                $totalStokKosongCount++;
            }
        }

        $chunks = $this->barangs->chunk($this->pairs);

        return [
            'barangs' => $this->barangs,
            'stok' => $this->stok,
            'pairs' => $this->pairs,
            'chunks' => $chunks,
            'totalBarang' => $totalBarang,
            'totalStokAktifCount' => $totalStokAktifCount,
            'totalStokKosongCount' => $totalStokKosongCount,
            'totalAkumulasiStok' => $totalAkumulasiStok,
            'totalAkumulasiM3' => $totalAkumulasiM3,
        ];
    }
}
