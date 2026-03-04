<?php

namespace App\Filament\Pages;

use App\Models\AkunGroup;
use App\Models\BukuBesar;
use App\Models\AnakAkun;
use App\Models\SubAnakAkun;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use App\Models\JurnalUmum;
use Carbon\Carbon;
use BackedEnum;
use UnitEnum;

class LabaRugi extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected static ?string $title = 'Laporan Laba Rugi';

    protected string $view = 'filament.pages.laba-rugi';

    public ?int $tahun        = null;
    public ?int $bulan_dari   = null;
    public ?int $bulan_sampai = null;
    public array $data        = [];

    public array $laporanData = [];
    public array $ringkasan   = [];
    public bool  $sudahFilter = false;

    /*
    |--------------------------------------------------------------------------
    | TIPE GROUP
    |
    | pendapatan      → + masuk ke Pendapatan Usaha
    | retur_potongan  → - dikurangkan dari Pendapatan → hasil = Penjualan Bersih
    | hpp             → - dikurangkan dari Penjualan Bersih → hasil = Laba Kotor
    | beban_usaha     → - dikurangkan dari Laba Kotor → hasil = Laba Usaha
    | pendapatan_lain → + ditambahkan ke Laba Usaha
    | beban_lain      → - dikurangkan → hasil = Laba Sebelum Pajak
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | LIFECYCLE
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->tahun        = now()->year;
        $this->bulan_dari   = now()->month;
        $this->bulan_sampai = now()->month;

        $this->schema->fill([
            'tahun'        => $this->tahun,
            'bulan_dari'   => $this->bulan_dari,
            'bulan_sampai' => $this->bulan_sampai,
        ]);

        $this->generateLaporan();
    }

    /*
    |--------------------------------------------------------------------------
    | FORM FILTER
    |--------------------------------------------------------------------------
    */

    public function schema(Schema $schema): Schema
    {
        $bulanOptions = [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $tahunOptions = collect(range(now()->year, now()->year - 5))
            ->mapWithKeys(fn($y) => [$y => (string) $y])
            ->toArray();

        return $schema
            ->schema([
                Grid::make(3)->schema([
                    Select::make('tahun')
                        ->label('Tahun')
                        ->options($tahunOptions)
                        ->required()
                        ->live(),
                    Select::make('bulan_dari')
                        ->label('Dari Bulan')
                        ->options($bulanOptions)
                        ->required()
                        ->live(),
                    Select::make('bulan_sampai')
                        ->label('Sampai Bulan')
                        ->options($bulanOptions)
                        ->required()
                        ->live(),
                ]),
            ])
            ->statePath('data');
    }

    /*
    |--------------------------------------------------------------------------
    | ACTION: FILTER SUBMIT
    |--------------------------------------------------------------------------
    */

    public function filter(): void
    {
        $this->validate([
            'data.tahun'        => 'required|integer',
            'data.bulan_dari'   => 'required|integer|min:1|max:12',
            'data.bulan_sampai' => 'required|integer|min:1|max:12',
        ]);

        $this->tahun        = (int) ($this->data['tahun']        ?? now()->year);
        $this->bulan_dari   = (int) ($this->data['bulan_dari']   ?? now()->month);
        $this->bulan_sampai = (int) ($this->data['bulan_sampai'] ?? now()->month);

        $this->generateLaporan();
    }

    /*
    |--------------------------------------------------------------------------
    | CORE: GENERATE LAPORAN
    |--------------------------------------------------------------------------
    */

    public function generateLaporan(): void
    {
        // Ambil saldo dari BukuBesar untuk periode filter
        $saldoMap = $this->getSaldoMap();

        // Cari root group "Laba Rugi"
        $rootLabaRugi = AkunGroup::whereNull('parent_id')
            ->whereRaw('LOWER(nama) = ?', ['laba rugi'])
            ->first();

        if (!$rootLabaRugi) {
            $rootLabaRugi = AkunGroup::whereNull('parent_id')
                ->whereRaw('LOWER(nama) LIKE ?', ['%laba rugi%'])
                ->first();
        }

        if (!$rootLabaRugi) {
            $this->laporanData = [];
            $this->ringkasan   = [];
            $this->sudahFilter = true;
            return;
        }

        // Ambil child groups dari root, urut by order
        $childGroups = AkunGroup::where('parent_id', $rootLabaRugi->id)
            ->visible()
            ->ordered()
            ->with([
                'childrenRecursive.anakAkuns.subAnakAkuns',
                'anakAkuns.subAnakAkuns',
            ])
            ->get();

        $sections  = [];
        $ringkasan = [
            'pendapatan'      => 0,
            'retur_potongan'  => 0,
            'hpp'             => 0,
            'beban_produksi'  => 0,
            'beban_usaha'     => 0,
            'pendapatan_lain' => 0,
            'beban_lain'      => 0,
            'lainnya'         => 0,
        ];

        foreach ($childGroups as $group) {
            $node       = $this->buildGroupNode($group, $saldoMap);
            $sections[] = $node;

            $tipe = $group->tipe ?? 'lainnya';
            if (array_key_exists($tipe, $ringkasan)) {
                $ringkasan[$tipe] += $node['total_nilai'];
            }
        }

        /*
         * RUMUS (mirip CoReTax):
         *
         * Penjualan Bersih     = Pendapatan - Retur & Potongan
         * Laba Kotor           = Penjualan Bersih - HPP - Beban Produksi
         * Laba (Rugi) Usaha    = Laba Kotor - Beban Usaha
         * Laba Sebelum Pajak   = Laba Usaha + Pendapatan Lain - Beban Lain
         */
        $totalPendapatan    = $ringkasan['pendapatan'];
        $totalReturPotongan = $ringkasan['retur_potongan'];
        $penjualanBersih    = $totalPendapatan - $totalReturPotongan;
        $totalHPP           = $ringkasan['hpp'] + $ringkasan['beban_produksi'];
        $labaKotor          = $penjualanBersih - $totalHPP;
        $totalBebanUsaha    = $ringkasan['beban_usaha'];
        $labaUsaha          = $labaKotor - $totalBebanUsaha;
        $pendapatanLain     = $ringkasan['pendapatan_lain'];
        $bebanLain          = $ringkasan['beban_lain'];
        $labaSebelumPajak   = $labaUsaha + $pendapatanLain - $bebanLain;

        $this->laporanData = $sections;
        $this->ringkasan   = [
            'total_pendapatan'    => $totalPendapatan,
            'total_retur_potongan' => $totalReturPotongan,
            'penjualan_bersih'    => $penjualanBersih,
            'total_hpp'           => $totalHPP,
            'laba_kotor'          => $labaKotor,
            'total_beban_usaha'   => $totalBebanUsaha,
            'laba_usaha'          => $labaUsaha,
            'pendapatan_lain'     => $pendapatanLain,
            'beban_lain'          => $bebanLain,
            'laba_sebelum_pajak'  => $labaSebelumPajak,

            // Flag untuk blade
            'ada_retur_potongan'  => $totalReturPotongan != 0,
            'ada_hpp'             => $totalHPP != 0,
            'ada_beban_usaha'     => $totalBebanUsaha != 0,
            'ada_lain'            => ($pendapatanLain + $bebanLain) != 0,
        ];
        $this->sudahFilter = true;
    }

    /*
    |--------------------------------------------------------------------------
    | RECURSIVE NODE BUILDER
    |--------------------------------------------------------------------------
    */

    private function buildGroupNode(AkunGroup $group, array $saldoMap): array
    {
        $children   = [];
        $totalNilai = 0;

        foreach ($group->children as $childGroup) {
            $node       = $this->buildGroupNode($childGroup, $saldoMap);
            $children[] = $node;
            $totalNilai += $node['total_nilai'];
        }

        foreach ($group->anakAkuns as $anak) {
            $node       = $this->buildAnakAkunNode($anak, $saldoMap);
            $children[] = $node;
            $totalNilai += $node['total_nilai'];
        }

        return [
            'type'        => 'group',
            'id'          => $group->id,
            'nama'        => $group->nama,
            'tipe'        => $group->tipe ?? 'lainnya',
            'hidden'      => $group->hidden,
            'children'    => $children,
            'total_nilai' => $totalNilai,
        ];
    }

    private function buildAnakAkunNode(AnakAkun $anak, array $saldoMap): array
    {
        $children   = [];
        $totalNilai = 0;
        $nilaiAnak  = $saldoMap[$anak->kode_anak_akun] ?? null;

        foreach ($anak->subAnakAkuns as $sub) {
            $node       = $this->buildSubAnakAkunNode($sub, $saldoMap);
            $children[] = $node;
            $totalNilai += $node['nilai'];
        }

        if (empty($children)) {
            $totalNilai = $nilaiAnak ?? 0;
        } else {
            if ($nilaiAnak !== null) {
                $totalNilai += $nilaiAnak;
            }
        }

        return [
            'type'        => 'anak_akun',
            'kode'        => $anak->kode_anak_akun,
            'nama'        => $anak->nama_anak_akun,
            'children'    => $children,
            'total_nilai' => $totalNilai,
            'nilai'       => $nilaiAnak ?? 0,
        ];
    }

    private function buildSubAnakAkunNode(SubAnakAkun $sub, array $saldoMap): array
    {
        return [
            'type'     => 'sub_anak_akun',
            'kode'     => $sub->kode_sub_anak_akun,
            'nama'     => $sub->nama_sub_anak_akun,
            'nilai'    => $saldoMap[$sub->kode_sub_anak_akun] ?? 0,
            'children' => [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Ambil saldo dari BukuBesar untuk periode filter.
     * BukuBesar sudah menyimpan saldo per bulan+tahun per akun,
     * jadi cukup sum saldo untuk range bulan yang dipilih.
     */
    private function getSaldoMap(): array
    {
        $tahun       = (int) $this->tahun;
        $bulanDari   = (int) $this->bulan_dari;
        $bulanSampai = (int) $this->bulan_sampai;

        $start = Carbon::create($tahun, $bulanDari, 1)->startOfMonth();
        $end   = Carbon::create($tahun, $bulanSampai, 1)->endOfMonth();

        $map = [];

        // 🔹 Ambil saldo normal semua akun
        $saldoNormalMap =
            AnakAkun::pluck('saldo_normal', 'kode_anak_akun')->toArray()
            + SubAnakAkun::pluck('saldo_normal', 'kode_sub_anak_akun')->toArray();

        // 🔹 Ambil transaksi jurnal
        $jurnals = JurnalUmum::whereBetween('tgl', [$start, $end])->get();

        foreach ($jurnals as $jurnal) {

            $kode  = $jurnal->no_akun;
            $nilai = ($jurnal->banyak ?? 0) * ($jurnal->harga ?? 0);

            $saldoNormal = strtolower($saldoNormalMap[$kode] ?? 'debit');
            $isDebit     = strtolower($jurnal->map) === 'd';

            if ($saldoNormal === 'kredit') {

                if ($isDebit) {
                    $map[$kode] = ($map[$kode] ?? 0) - $nilai;
                } else {
                    $map[$kode] = ($map[$kode] ?? 0) + $nilai;
                }
            } else {

                if ($isDebit) {
                    $map[$kode] = ($map[$kode] ?? 0) + $nilai;
                } else {
                    $map[$kode] = ($map[$kode] ?? 0) - $nilai;
                }
            }
        }

        return $map;
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW HELPERS
    |--------------------------------------------------------------------------
    */

    public function getNamaBulan(int $bulan): string
    {
        return [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ][$bulan] ?? '';
    }

    public function formatRupiah(float $nilai): string
    {
        return 'Rp ' . number_format(abs($nilai), 0, ',', '.');
    }
}
