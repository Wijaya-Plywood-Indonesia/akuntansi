<?php

namespace App\Filament\Pages;

use App\Models\IndukAkun;
use App\Models\JurnalUmum;
use App\Models\Barang;
use App\Models\BukuBesar as BukuBesarModel;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Carbon\Carbon;
use UnitEnum;
use Illuminate\Support\Facades\DB;

class BukuBesar extends Page
{
    use HasPageShield;

    protected static string|UnitEnum|null $navigationGroup = 'Jurnal & Akuntansi';
    protected string $view = 'filament.pages.buku-besar';
    protected static ?string $navigationLabel = 'Buku Besar';
    protected static ?string $title = 'Buku Besar';

    public $indukAkuns = [];
    public $filterBulan;
    public bool $isLoading = true;
    public $saldoMap = [];
    public $saldoAwalMap = [];
    public $saldoAwalQtyMap = [];   // qty berbasis 'banyak'
    public $saldoAwalM3Map = [];    // qty berbasis 'm3'

    /** Kode akun yang merupakan akun persediaan SATU barang
     *  (id_sub_anak_akun di tabel barangs). Hanya akun-akun ini yang
     *  qty/m3-nya bermakna sebagai kuantitas fisik. */
    public $persediaanKodes = [];

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('Y-m');
        // isLoading = true by default, initLoad akan dipanggil via wire:init
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    return \Maatwebsite\Excel\Facades\Excel::download(
                        new \App\Exports\BukuBesarExport($this->filterBulan),
                        'Buku_Besar_' . $this->filterBulan . '.xlsx'
                    );
                }),
        ];
    }

    public function initLoad(): void
    {
        $this->preloadPersediaanKodes();
        $this->preloadSaldoAwal();
        $this->preloadSaldo();
        $this->loadData();
        $this->isLoading = false;
    }

    public function updatedFilterBulan(): void
    {
        $this->isLoading       = true;
        $this->saldoAwalMap    = [];
        $this->saldoAwalQtyMap = [];
        $this->saldoAwalM3Map  = [];
        $this->saldoMap        = [];

        $this->preloadSaldoAwal();
        $this->preloadSaldo();
        $this->loadData();
        $this->isLoading = false;
    }

    // ── Kumpulkan kode akun yang merupakan akun persediaan SATU barang ───────
    // (sama persis dengan pendekatan web telur — barangs.id_sub_anak_akun).
    private function preloadPersediaanKodes(): void
    {
        $this->persediaanKodes = Barang::with('subAnakAkun')
            ->get()
            ->map(fn($b) => $b->subAnakAkun?->kode_sub_anak_akun)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    // ── Cek apakah suatu kode akun adalah akun persediaan satu barang ────────
    public function isPersediaanAkun(?string $kode): bool
    {
        return $kode && in_array($kode, $this->persediaanKodes, true);
    }

    // PENTING: Web akuntansi TIDAK punya proses tutup buku bulanan yang
    // berjalan (command app:closing-buku-besar masih kosong/stub), jadi
    // tabel snapshot 'buku_besar' tidak pernah terisi — membacanya selalu
    // balik 0, meski transaksi historis sebenarnya ada.
    // Solusinya: hitung saldo awal langsung dari JurnalUmum (live), dengan
    // menjumlah SEMUA transaksi SEBELUM awal bulan yang dipilih.
    // Nominal mengikuti hit_kbk (b = banyak*harga, m = m3*harga, default = harga),
    // sama seperti perhitungan mutasi bulan berjalan di preloadSaldo().
    private function preloadSaldoAwal(): void
    {
        $end = Carbon::parse($this->filterBulan)->startOfMonth();

        $rows = JurnalUmum::where('tgl', '<', $end)
            ->selectRaw("
                no_akun,
                SUM(CASE WHEN LOWER(map) = 'd' THEN
                    CASE
                        WHEN LOWER(hit_kbk) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
                        WHEN LOWER(hit_kbk) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
                        ELSE COALESCE(harga, 0)
                    END
                ELSE 0 END) as total_debit,
                SUM(CASE WHEN LOWER(map) = 'k' THEN
                    CASE
                        WHEN LOWER(hit_kbk) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
                        WHEN LOWER(hit_kbk) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
                        ELSE COALESCE(harga, 0)
                    END
                ELSE 0 END) as total_kredit,
                SUM(CASE WHEN LOWER(map) = 'd' THEN COALESCE(banyak, 0) ELSE 0 END) as total_qty_debit,
                SUM(CASE WHEN LOWER(map) = 'k' THEN COALESCE(banyak, 0) ELSE 0 END) as total_qty_kredit,
                SUM(CASE WHEN LOWER(map) = 'd' THEN COALESCE(m3, 0) ELSE 0 END) as total_m3_debit,
                SUM(CASE WHEN LOWER(map) = 'k' THEN COALESCE(m3, 0) ELSE 0 END) as total_m3_kredit
            ")
            ->groupBy('no_akun')
            ->get();

        $this->saldoAwalMap    = [];
        $this->saldoAwalQtyMap = [];
        $this->saldoAwalM3Map  = [];

        foreach ($rows as $row) {
            // Disimpan dalam konvensi "debit positif" (raw, belum disesuaikan
            // saldo_normal). Penyesuaian tanda untuk akun kredit-normal tetap
            // dilakukan di getTotalRecursive() / getTotalRecursiveQty() /
            // getTotalRecursiveM3(), sama seperti mutasi bulan berjalan.
            $this->saldoAwalMap[$row->no_akun]    = (float) $row->total_debit - (float) $row->total_kredit;
            $this->saldoAwalQtyMap[$row->no_akun] = (float) $row->total_qty_debit - (float) $row->total_qty_kredit;
            $this->saldoAwalM3Map[$row->no_akun]  = (float) $row->total_m3_debit - (float) $row->total_m3_kredit;
        }
    }

    // ── Mutasi bulan terpilih dari jurnal_umums ──────────────────────────────
    // Simpan debit dan kredit GROSS terpisah — bukan net. Nominal mengikuti
    // hit_kbk, dan sekarang juga menyimpan gross qty (banyak) & m3.
    private function preloadSaldo(): void
    {
        $start = Carbon::parse($this->filterBulan)->startOfMonth();
        $end   = Carbon::parse($this->filterBulan)->endOfMonth();

        $rows = JurnalUmum::whereBetween('tgl', [$start, $end])
            ->selectRaw("
                no_akun,
                SUM(CASE WHEN LOWER(map) = 'd' THEN
                    CASE
                        WHEN LOWER(hit_kbk) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
                        WHEN LOWER(hit_kbk) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
                        ELSE COALESCE(harga, 0)
                    END
                ELSE 0 END) as total_debit,
                SUM(CASE WHEN LOWER(map) = 'k' THEN
                    CASE
                        WHEN LOWER(hit_kbk) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
                        WHEN LOWER(hit_kbk) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
                        ELSE COALESCE(harga, 0)
                    END
                ELSE 0 END) as total_kredit,
                SUM(CASE WHEN LOWER(map) = 'd' THEN COALESCE(banyak, 0) ELSE 0 END) as total_qty_debit,
                SUM(CASE WHEN LOWER(map) = 'k' THEN COALESCE(banyak, 0) ELSE 0 END) as total_qty_kredit,
                SUM(CASE WHEN LOWER(map) = 'd' THEN COALESCE(m3, 0) ELSE 0 END) as total_m3_debit,
                SUM(CASE WHEN LOWER(map) = 'k' THEN COALESCE(m3, 0) ELSE 0 END) as total_m3_kredit
            ")
            ->groupBy('no_akun')
            ->get();

        $this->saldoMap = [];
        foreach ($rows as $row) {
            $this->saldoMap[$row->no_akun] = [
                'd'      => (float) $row->total_debit,
                'k'      => (float) $row->total_kredit,
                'd_qty'  => (float) $row->total_qty_debit,
                'k_qty'  => (float) $row->total_qty_kredit,
                'd_m3'   => (float) $row->total_m3_debit,
                'k_m3'   => (float) $row->total_m3_kredit,
            ];
        }
    }

    public function loadData(): void
    {
        $this->indukAkuns = IndukAkun::with([
            'anakAkuns' => function ($query) {
                $query->whereNull('parent')
                    ->with([
                        'subAnakAkuns',
                        'children' => function ($q) {
                            $q->with([
                                'subAnakAkuns',
                                'children' => function ($q2) {
                                    $q2->with(['subAnakAkuns']);
                                },
                            ]);
                        },
                    ]);
            },
        ])->get();
    }

    // ── Hitung nominal satu transaksi mengikuti hit_kbk ──────────────────────
    public function hitungNominal($trx): float
    {
        return match (strtolower($trx->hit_kbk ?? '')) {
            'b'     => (float) ($trx->banyak ?? 0) * (float) ($trx->harga ?? 0),
            'm'     => (float) ($trx->m3 ?? 0)     * (float) ($trx->harga ?? 0),
            default => (float) ($trx->harga ?? 0),
        };
    }

    // ── Saldo awal Rupiah (raw debit-net, dihitung live dari JurnalUmum) ────
    public function getSaldoAwal(string $kode): float
    {
        return (float) ($this->saldoAwalMap[$kode] ?? 0);
    }

    // ── Saldo awal QTY (banyak) — hanya bermakna untuk akun persediaan ──────
    public function getSaldoAwalQty(string $kode): ?float
    {
        if (!$this->isPersediaanAkun($kode)) {
            return null;
        }
        return (float) ($this->saldoAwalQtyMap[$kode] ?? 0);
    }

    // ── Saldo awal M3 — hanya bermakna untuk akun persediaan ────────────────
    public function getSaldoAwalM3(string $kode): ?float
    {
        if (!$this->isPersediaanAkun($kode)) {
            return null;
        }
        return (float) ($this->saldoAwalM3Map[$kode] ?? 0);
    }

    // ── Total QTY (banyak) kumulatif = saldo awal qty + mutasi qty bulan ini ─
    public function getSaldoQtyKumulatif(string $kode): ?float
    {
        if (!$this->isPersediaanAkun($kode)) {
            return null;
        }
        $awal = (float) ($this->saldoAwalQtyMap[$kode] ?? 0);
        $d    = (float) ($this->saldoMap[$kode]['d_qty'] ?? 0);
        $k    = (float) ($this->saldoMap[$kode]['k_qty'] ?? 0);
        return $awal + $d - $k;
    }

    // ── Total M3 kumulatif = saldo awal m3 + mutasi m3 bulan ini ─────────────
    public function getSaldoM3Kumulatif(string $kode): ?float
    {
        if (!$this->isPersediaanAkun($kode)) {
            return null;
        }
        $awal = (float) ($this->saldoAwalM3Map[$kode] ?? 0);
        $d    = (float) ($this->saldoMap[$kode]['d_m3'] ?? 0);
        $k    = (float) ($this->saldoMap[$kode]['k_m3'] ?? 0);
        return $awal + $d - $k;
    }

    // ── Mutasi bulan ini untuk satu akun (debit gross) ───────────────────────
    public function getSaldoBulan(string $kode): float
    {
        return (float) ($this->saldoMap[$kode]['d'] ?? 0)
            - (float) ($this->saldoMap[$kode]['k'] ?? 0);
    }

    // ── Transaksi bulan terpilih untuk satu kode akun ───────────────────────
    public function getTransaksiByKode(string $kode)
    {
        $start = Carbon::parse($this->filterBulan)->startOfMonth();
        $end   = Carbon::parse($this->filterBulan)->endOfMonth();

        return JurnalUmum::where('no_akun', $kode)
            ->whereBetween('tgl', [$start, $end])
            ->orderBy('tgl', 'asc')
            ->orderBy('jurnal', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    // ── Saldo rekursif berdasarkan saldo_normal akun ─────────────────────────
    public function getTotalRecursive($akun): float
    {
        $total = 0.0;

        $kode = $akun->kode_anak_akun ?? $akun->kode_sub_anak_akun ?? null;

        $saldoNormal = strtolower($akun->saldo_normal ?? 'debit');
        $isKredit    = in_array($saldoNormal, ['kredit', 'credit', 'k']);

        // saldoAwalMap disimpan RAW debit-net (debit positif). Untuk akun
        // kredit-normal (Pendapatan, Utang, Modal), tandanya harus dibalik
        // dulu supaya konsisten dengan cara mutasi bulan ini diperlakukan.
        $saldoAwalRaw = (float) ($this->saldoAwalMap[$kode] ?? 0);
        $saldoAwal    = $isKredit ? -$saldoAwalRaw : $saldoAwalRaw;

        if ($kode && isset($this->saldoMap[$kode])) {
            $debit  = (float) ($this->saldoMap[$kode]['d'] ?? 0);
            $kredit = (float) ($this->saldoMap[$kode]['k'] ?? 0);

            if ($isKredit) {
                $total += $saldoAwal + $kredit - $debit;
            } else {
                $total += $saldoAwal + $debit - $kredit;
            }
        } elseif ($kode) {
            $total += $saldoAwal;
        }

        if (isset($akun->children)) {
            foreach ($akun->children as $child) {
                $total += $this->getTotalRecursive($child);
            }
        }

        if (isset($akun->subAnakAkuns)) {
            foreach ($akun->subAnakAkuns as $sub) {
                $total += $this->getTotalRecursive($sub);
            }
        }

        return $total;
    }

    // ── Versi QTY (banyak) dari getTotalRecursive() ──────────────────────────
    // Hanya menjumlah akun persediaan satu barang; akun lain kontribusi 0.
    public function getTotalRecursiveQty($akun): float
    {
        $total = 0.0;

        $kode = $akun->kode_anak_akun ?? $akun->kode_sub_anak_akun ?? null;

        if ($kode && $this->isPersediaanAkun($kode)) {
            $saldoNormal = strtolower($akun->saldo_normal ?? 'debit');
            $isKredit    = in_array($saldoNormal, ['kredit', 'credit', 'k']);

            $awalRaw = (float) ($this->saldoAwalQtyMap[$kode] ?? 0);
            $dQty    = (float) ($this->saldoMap[$kode]['d_qty'] ?? 0);
            $kQty    = (float) ($this->saldoMap[$kode]['k_qty'] ?? 0);

            $total += $isKredit
                ? (-$awalRaw + $kQty - $dQty)
                : ($awalRaw + $dQty - $kQty);
        }

        if (isset($akun->children)) {
            foreach ($akun->children as $child) {
                $total += $this->getTotalRecursiveQty($child);
            }
        }

        if (isset($akun->subAnakAkuns)) {
            foreach ($akun->subAnakAkuns as $sub) {
                $total += $this->getTotalRecursiveQty($sub);
            }
        }

        return $total;
    }

    // ── Versi M3 dari getTotalRecursive() ────────────────────────────────────
    // Hanya menjumlah akun persediaan satu barang; akun lain kontribusi 0.
    public function getTotalRecursiveM3($akun): float
    {
        $total = 0.0;

        $kode = $akun->kode_anak_akun ?? $akun->kode_sub_anak_akun ?? null;

        if ($kode && $this->isPersediaanAkun($kode)) {
            $saldoNormal = strtolower($akun->saldo_normal ?? 'debit');
            $isKredit    = in_array($saldoNormal, ['kredit', 'credit', 'k']);

            $awalRaw = (float) ($this->saldoAwalM3Map[$kode] ?? 0);
            $dM3     = (float) ($this->saldoMap[$kode]['d_m3'] ?? 0);
            $kM3     = (float) ($this->saldoMap[$kode]['k_m3'] ?? 0);

            $total += $isKredit
                ? (-$awalRaw + $kM3 - $dM3)
                : ($awalRaw + $dM3 - $kM3);
        }

        if (isset($akun->children)) {
            foreach ($akun->children as $child) {
                $total += $this->getTotalRecursiveM3($child);
            }
        }

        if (isset($akun->subAnakAkuns)) {
            foreach ($akun->subAnakAkuns as $sub) {
                $total += $this->getTotalRecursiveM3($sub);
            }
        }

        return $total;
    }
}