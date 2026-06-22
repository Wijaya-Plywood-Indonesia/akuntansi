<?php

namespace App\Filament\Pages;

use App\Models\Penjualan;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class LeaderboardPenjualanUser extends Page
{
    use HasPageShield;

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    protected string $view = 'filament.pages.leaderboard-penjualan-user';

    protected static ?string $navigationLabel = 'Leaderboard';

    protected static ?string $title = 'Leaderboard Penjualan';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    public ?string $selectedCustomer = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public string $sortBy = 'belanja'; // 'belanja' or 'transaksi'

    public function showCustomer(string $name): void
    {
        $this->selectedCustomer = $name;
    }

    public function closeCustomer(): void
    {
        $this->selectedCustomer = null;
    }

    public function updatedStartDate($value): void
    {
        $today = now()->setTimezone('Asia/Jakarta')->format('Y-m-d');
        if ($value && $value > $today) {
            $this->startDate = $today;
        }

        if ($this->startDate && $this->endDate && $this->startDate > $this->endDate) {
            $this->endDate = $this->startDate;
        }
    }

    public function updatedEndDate($value): void
    {
        $today = now()->setTimezone('Asia/Jakarta')->format('Y-m-d');
        if ($value && $value > $today) {
            $this->endDate = $today;
        }

        if ($this->startDate && $this->endDate && $this->endDate < $this->startDate) {
            $this->startDate = $this->endDate;
        }
    }

    public function resetFilters(): void
    {
        $this->startDate = null;
        $this->endDate = null;
    }

    public function getViewData(): array
    {
        // Query database to aggregate purchases by customer
        $query = Penjualan::query()
            ->select(
                DB::raw("CASE WHEN nama_customer IS NULL OR TRIM(nama_customer) = '' THEN 'Customer' ELSE nama_customer END as customer_name"),
                DB::raw('SUM(total) as total_belanja'),
                DB::raw('COUNT(*) as total_transaksi')
            )
            ->where('status_transaksi', '!=', 'DIBATALKAN');

        // Apply date filter
        if ($this->startDate) {
            $query->whereDate('tanggal', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('tanggal', '<=', $this->endDate);
        }

        $sortByField = $this->sortBy === 'transaksi' ? 'total_transaksi' : 'total_belanja';
        $secondarySortField = $this->sortBy === 'transaksi' ? 'total_belanja' : 'total_transaksi';

        $records = $query->groupBy(DB::raw("CASE WHEN nama_customer IS NULL OR TRIM(nama_customer) = '' THEN 'Customer' ELSE nama_customer END"))
            ->orderByDesc($sortByField)
            ->orderByDesc($secondarySortField)
            ->get()
            ->map(function ($row) {
                // Generate a randomized-looking but deterministic avatar url using the customer name as seed
                // Dicebear URL with bottts-neutral style
                $seed = urlencode($row->customer_name);
                $avatar = "https://api.dicebear.com/10.x/bottts-neutral/svg?seed={$seed}";

                return (object) [
                    'name' => $row->customer_name,
                    'total_belanja' => (float) $row->total_belanja,
                    'total_transaksi' => (int) $row->total_transaksi,
                    'avatar' => $avatar,
                ];
            });

        // Split into Top 3 for the podium and the rest for the normal list
        $top3 = $records->take(3);

        // Re-arrange top3 to fit the podium layout: [2nd, 1st, 3rd]
        $podium = [];
        if ($top3->has(1)) {
            $podium[] = $top3->get(1); // 2nd Place
        } else {
            $podium[] = null;
        }

        if ($top3->has(0)) {
            $podium[] = $top3->get(0); // 1st Place
        } else {
            $podium[] = null;
        }

        if ($top3->has(2)) {
            $podium[] = $top3->get(2); // 3rd Place
        } else {
            $podium[] = null;
        }

        $others = $records->slice(3)->values()->all();

        $customerTransactions = [];
        if ($this->selectedCustomer) {
            $txQuery = Penjualan::query()
                ->with(['details'])
                ->where('status_transaksi', '!=', 'DIBATALKAN')
                ->where(function ($query) {
                    if ($this->selectedCustomer === 'Customer') {
                        $query->whereNull('nama_customer')
                            ->orWhere(DB::raw('TRIM(nama_customer)'), '');
                    } else {
                        $query->where('nama_customer', $this->selectedCustomer);
                    }
                });

            // Apply date filter
            if ($this->startDate) {
                $txQuery->whereDate('tanggal', '>=', $this->startDate);
            }
            if ($this->endDate) {
                $txQuery->whereDate('tanggal', '<=', $this->endDate);
            }

            $customerTransactions = $txQuery->orderByDesc('tanggal')
                ->get()
                ->all();
        }

        return [
            'podium' => $podium,
            'others' => $others,
            'top3_raw' => $top3->all(),
            'customerTransactions' => $customerTransactions,
        ];
    }
}
