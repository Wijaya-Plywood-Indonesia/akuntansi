<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Models\DetailPenjualan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use UnitEnum;

class LeaderboardPenjualan extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;
    // protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Leaderboard Barang';
    protected static ?string $title = 'Leaderboard Barang';
    protected static UnitEnum|string|null $navigationGroup = 'Leaderboard';

    protected string $view = 'filament.pages.leaderboard-penjualan';

    // State untuk Filter
    public $startDate;
    public $endDate;
    public $sortBy = 'value'; // value, qty, nota
    
    // State untuk Modal Nota
    public $selectedBarangId = null;
    public $selectedBarangName = '';
    public $searchNota = '';

    public function mount()
    {
        // Default: Tanggal 1 bulan ini sampai hari ini
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');
    }

    /**
     * Mengambil data leaderboard berdasarkan filter
     */
    public function getLeaderboardDataProperty()
    {
        $query = DetailPenjualan::join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->join('barangs', 'penjualan_details.barang_id', '=', 'barangs.id')
            ->whereBetween('penjualans.tanggal', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->select(
                'barangs.id',
                'barangs.nama_barang as name',
                DB::raw('SUM(penjualan_details.qty) as qty'),
                DB::raw('SUM(penjualan_details.subtotal) as value'),
                DB::raw('COUNT(DISTINCT penjualans.id) as notaCount')
            )
            ->groupBy('barangs.id', 'barangs.nama_barang');

        $data = $query->get();

        // Terapkan Sorting
        if ($this->sortBy === 'qty') {
            $data = $data->sortByDesc('qty');
        } elseif ($this->sortBy === 'nota') {
            $data = $data->sortByDesc('notaCount');
        } else {
            $data = $data->sortByDesc('value');
        }

        // Berikan Rank
        $ranked = collect();
        $rank = 1;
        foreach ($data as $item) {
            $item->rank = $rank++;
            $ranked->push($item);
        }

        return $ranked;
    }

    /**
     * Mengambil data list nota untuk modal yang sedang dibuka
     */
    public function getNotasDataProperty()
    {
        if (!$this->selectedBarangId) {
            return [];
        }

        return DetailPenjualan::with('penjualan')
            ->join('penjualans', 'penjualan_details.penjualan_id', '=', 'penjualans.id')
            ->where('penjualan_details.barang_id', $this->selectedBarangId)
            ->whereBetween('penjualans.tanggal', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->when($this->searchNota, function ($q) {
                $q->where(function ($subQ) {
                    $subQ->where('penjualans.no_nota', 'like', '%' . $this->searchNota . '%')
                         ->orWhere('penjualans.nama_customer', 'like', '%' . $this->searchNota . '%');
                });
            })
            ->select('penjualan_details.*')
            ->get();
    }

    // --- Action Methods ---

    public function setSortBy($type)
    {
        $this->sortBy = $type;
    }

    public function openModal($id, $name)
    {
        $this->selectedBarangId = $id;
        $this->selectedBarangName = $name;
        $this->searchNota = ''; // Reset search setiap kali buka modal
    }

    public function closeModal()
    {
        $this->selectedBarangId = null;
        $this->selectedBarangName = '';
    }
}