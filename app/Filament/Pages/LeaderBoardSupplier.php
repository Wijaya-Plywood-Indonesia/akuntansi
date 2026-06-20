<?php

namespace App\Filament\Pages;

use App\Models\Pembelian;
use App\Models\Supplier;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class LeaderBoardSupplier extends Page
{
    // protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Leaderboard Supplier';
    protected static ?string $title = 'Leaderboard Supplier';
    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.leader-board-supplier';

    public string $search = '';

    /**
     * Ambil data leaderboard supplier dari DB.
     * Diurutkan berdasarkan grand_total DESC (hanya status: hutang, cicilan, lunas).
     */
    public function getLeaderboardData(): \Illuminate\Support\Collection
    {
        $validStatuses = [
            Pembelian::STATUS_HUTANG,
            Pembelian::STATUS_CICILAN,
            Pembelian::STATUS_LUNAS,
        ];

        $query = Pembelian::query()
            ->select([
                'supplier_id',
                'supplier_name',
                DB::raw('SUM(grand_total) as total_pembelian'),
                DB::raw('COUNT(id) as nota_dicetak'),
            ])
            ->whereIn('status', $validStatuses)
            ->whereNotNull('supplier_id')
            ->groupBy('supplier_id', 'supplier_name')
            ->orderByDesc('total_pembelian');

        if ($this->search) {
            $query->where('supplier_name', 'like', '%' . $this->search . '%');
        }

        $results = $query->get();

        // Tambahkan rank ke setiap baris
        return $results->map(function ($item, $index) {
            $item->rank = $index + 1;
            return $item;
        });
    }

    /**
     * Ambil detail invoices untuk satu supplier (untuk slide-over via Livewire).
     */
    public function getSupplierDetail(int $supplierId): array
    {
        $validStatuses = [
            Pembelian::STATUS_HUTANG,
            Pembelian::STATUS_CICILAN,
            Pembelian::STATUS_LUNAS,
        ];

        $supplier = Supplier::find($supplierId);

        $invoices = Pembelian::query()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', $validStatuses)
            ->select(['id', 'nomor_nota', 'tanggal', 'grand_total', 'status'])
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'nomor_nota' => $p->nomor_nota,
                'tanggal'    => $p->tanggal?->format('Y-m-d'),
                'grand_total' => (float) $p->grand_total,
                'status'     => $p->status,
            ])
            ->toArray();

        $totalPembelian = collect($invoices)->sum('grand_total');

        return [
            'supplier_id'    => $supplierId,
            'name'           => $supplier?->name ?? 'Unknown',
            'total_pembelian' => $totalPembelian,
            'nota_dicetak'   => count($invoices),
            'invoices'       => $invoices,
        ];
    }

    /**
     * Livewire listener untuk search bar update.
     */
    public function updatedSearch(): void
    {
        // Blade akan reactive karena $search adalah public property Livewire
    }

    /**
     * Helpers yang di-pass ke view.
     */
    protected function getViewData(): array
    {
        return [
            'leaderboard' => $this->getLeaderboardData(),
        ];
    }
}
