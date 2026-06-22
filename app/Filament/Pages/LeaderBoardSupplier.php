<?php

namespace App\Filament\Pages;

use App\Models\Pembelian;
use App\Models\Supplier;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class LeaderBoardSupplier extends Page
{
    // protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Leaderboard Supplier';
    protected static ?string $title = 'Leaderboard Supplier';
    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.leader-board-supplier';

    /**
     * ============================================================
     * PUBLIC PROPERTIES (Livewire akan reactive on change)
     * ============================================================
     */
    public string $search = '';
    public string $filterDari = '';   // Tanggal mulai (YYYY-MM-DD format)
    public string $filterSampai = ''; // Tanggal akhir (YYYY-MM-DD format)

    /**
     * ============================================================
     * LISTENERS - Handle perubahan property
     * ============================================================
     * Livewire otomatis trigger "updated{PropertyName}" ketika property berubah
     */
    #[\Livewire\Attributes\On('updated')]
    public function updatedFilterDari(): void
    {
        // Trigger re-render ketika filterDari berubah
        // (tidak perlu logic apapun, Livewire handle otomatis)
    }

    #[\Livewire\Attributes\On('updated')]
    public function updatedFilterSampai(): void
    {
        // Trigger re-render ketika filterSampai berubah
    }

    #[\Livewire\Attributes\On('updated')]
    public function updatedSearch(): void
    {
        // Trigger re-render ketika search berubah
    }

    /**
     * ============================================================
     * HELPER - Parse dan validate tanggal
     * ============================================================
     */
    private function getParsedDateRange(): array
    {
        $dari = null;
        $sampai = null;

        // Jika ada filterDari, parse ke Carbon date
        if (!empty($this->filterDari)) {
            try {
                $dari = Carbon::createFromFormat('Y-m-d', $this->filterDari)->startOfDay();
            } catch (\Exception $e) {
                // Silently ignore invalid format
            }
        }

        // Jika ada filterSampai, parse ke Carbon date
        if (!empty($this->filterSampai)) {
            try {
                $sampai = Carbon::createFromFormat('Y-m-d', $this->filterSampai)->endOfDay();
            } catch (\Exception $e) {
                // Silently ignore invalid format
            }
        }

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'hasFilter' => !is_null($dari) && !is_null($sampai),
        ];
    }

    /**
     * ============================================================
     * MAIN QUERY - Ambil data leaderboard supplier
     * ============================================================
     * Diurutkan berdasarkan grand_total DESC (hanya status: hutang, cicilan, lunas).
     * Dengan support filter tanggal (WHERE tanggal BETWEEN).
     */
    public function getLeaderboardData(): \Illuminate\Support\Collection
    {
        $validStatuses = [
            Pembelian::STATUS_HUTANG,
            Pembelian::STATUS_CICILAN,
            Pembelian::STATUS_LUNAS,
        ];

        // Dapatkan parsed date range
        $dateRange = $this->getParsedDateRange();

        $query = Pembelian::query()
            ->select([
                'supplier_id',
                'supplier_name',
                DB::raw('SUM(grand_total) as total_pembelian'),
                DB::raw('COUNT(id) as nota_dicetak'),
            ])
            ->whereIn('status', $validStatuses)
            ->whereNotNull('supplier_id');

        // ✨ TAMBAHAN: Filter by date range (jika ada)
        if ($dateRange['hasFilter']) {
            $query->whereBetween('tanggal', [$dateRange['dari'], $dateRange['sampai']]);
        }

        // Filter search
        if ($this->search) {
            $query->where('supplier_name', 'like', '%' . $this->search . '%');
        }

        $results = $query
            ->groupBy('supplier_id', 'supplier_name')
            ->orderByDesc('total_pembelian')
            ->get();

        // Tambahkan rank ke setiap baris
        return $results->map(function ($item, $index) {
            $item->rank = $index + 1;
            return $item;
        });
    }

    /**
     * ============================================================
     * DETAIL - Ambil invoices untuk satu supplier
     * ============================================================
     * Dipanggil via AJAX dari JavaScript (Blade template).
     * Route: /internal/supplier-detail/{supplierId}
     */
    public function getSupplierDetail(int $supplierId): array
    {
        $validStatuses = [
            Pembelian::STATUS_HUTANG,
            Pembelian::STATUS_CICILAN,
            Pembelian::STATUS_LUNAS,
        ];

        $supplier = Supplier::find($supplierId);

        // Dapatkan parsed date range
        $dateRange = $this->getParsedDateRange();

        $query = Pembelian::query()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', $validStatuses);

        // ✨ TAMBAHAN: Filter by date range (jika ada)
        if ($dateRange['hasFilter']) {
            $query->whereBetween('tanggal', [$dateRange['dari'], $dateRange['sampai']]);
        }

        $invoices = $query
            ->select(['id', 'nomor_nota', 'tanggal', 'grand_total', 'status'])
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'nomor_nota'   => $p->nomor_nota,
                'tanggal'      => $p->tanggal?->format('Y-m-d'),
                'grand_total'  => (float) $p->grand_total,
                'status'       => $p->status,
            ])
            ->toArray();

        $totalPembelian = collect($invoices)->sum('grand_total');

        return [
            'supplier_id'      => $supplierId,
            'name'             => $supplier?->name ?? 'Unknown',
            'total_pembelian'  => $totalPembelian,
            'nota_dicetak'     => count($invoices),
            'invoices'         => $invoices,
        ];
    }

    /**
     * ============================================================
     * VIEW DATA - Pass ke Blade template
     * ============================================================
     */
    protected function getViewData(): array
    {
        return [
            'leaderboard' => $this->getLeaderboardData(),
        ];
    }
}
