<?php

namespace App\Filament\Pages;

use App\Models\AkunGroup;
use App\Models\BukuBesar;
use App\Models\AnakAkun;
use App\Models\SubAnakAkun;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Form;
use Filament\Schemas\Schema;
use BackedEnum;
use UnitEnum;

class LabaRugi extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    // protected static ?string $navigationLabel = 'Laba Rugi';
    protected static ?string $title = 'Laporan Laba Rugi';

    protected string $view = 'filament.pages.laba-rugi';

    public ?int $tahun        = null;
    public ?int $bulan_dari   = null;
    public ?int $bulan_sampai = null;
    public array $data        = [];

    // Hasil build — array siap render
    public array $laporanData  = [];
    public array $ringkasan    = [];   // subtotal per tipe untuk rumus
    public bool  $sudahFilter  = false;

    /*
    |--------------------------------------------------------------------------
    | Tipe → apakah nilai POSITIF atau NEGATIF dalam laporan
    |--------------------------------------------------------------------------
    | pendapatan      : + (menambah laba)
    | pendapatan_lain : + (menambah laba)
    | hpp             : - (mengurangi laba)
    | beban_produksi  : - (mengurangi laba, bagian dari HPP)
    | beban_usaha     : - (mengurangi laba)
    | beban_lain      : - (mengurangi laba)
    | lainnya         : ditampilkan apa adanya, tidak masuk rumus
    */
    private const TIPE_POSITIF = ['pendapatan', 'pendapatan_lain'];
    private const TIPE_NEGATIF = ['hpp', 'beban_produksi', 'beban_usaha', 'beban_lain'];

    /*
    |--------------------------------------------------------------------------
    | LIFECYCLE
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->tahun        = now()->year;
        $this->bulan_dari   = 1;
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
            1  => 'Januari',  2  => 'Februari', 3  => 'Maret',
            4  => 'April',    5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',     8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',  11 => 'November',  12 => 'Desember',
        ];

        $tahunOptions = collect(range(now()->year, now()->year - 5))
            ->mapWithKeys(fn ($y) => [$y => (string) $y])
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

        $this->tahun        = (int) $this->data['tahun'];
        $this->bulan_dari   = (int) $this->data['bulan_dari'];
        $this->bulan_sampai = (int) $this->data['bulan_sampai'];

        $this->generateLaporan();
    }

    /*
    |--------------------------------------------------------------------------
    | CORE: GENERATE LAPORAN
    |--------------------------------------------------------------------------
    */

    public function generateLaporan(): void
    {
        $saldoMap = $this->getSaldoMap();

        // Cari parent root bernama "Laba Rugi" (case-insensitive)
        $rootLabaRugi = AkunGroup::whereNull('parent_id')
            ->whereRaw('LOWER(nama) = ?', ['laba rugi'])
            ->first();

        if (!$rootLabaRugi) {
            // Fallback: coba cari yang mengandung kata "laba rugi"
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

        // Ambil child groups dari root "Laba Rugi", urut by order
        $childGroups = AkunGroup::where('parent_id', $rootLabaRugi->id)
            ->visible()
            ->ordered()
            ->with([
                'childrenRecursive.anakAkuns.subAnakAkuns',
                'anakAkuns.subAnakAkuns',
            ])
            ->get();

        // Build setiap section
        $sections   = [];
        $ringkasan  = [
            'pendapatan'      => 0,
            'hpp'             => 0,
            'beban_produksi'  => 0,
            'beban_usaha'     => 0,
            'pendapatan_lain' => 0,
            'beban_lain'      => 0,
            'lainnya'         => 0,
        ];

        foreach ($childGroups as $group) {
            $node = $this->buildGroupNode($group, $saldoMap);
            $sections[] = $node;

            // Akumulasi ringkasan per tipe
            $tipe = $group->tipe ?? 'lainnya';
            if (array_key_exists($tipe, $ringkasan)) {
                $ringkasan[$tipe] += $node['total_nilai'];
            }
        }

        // Hitung subtotal rumus
        $totalPendapatan = $ringkasan['pendapatan'];
        $totalHPP        = $ringkasan['hpp'] + $ringkasan['beban_produksi'];
        $labaKotor       = $totalPendapatan - $totalHPP;
        $totalBebanUsaha = $ringkasan['beban_usaha'];
        $labaUsaha       = $labaKotor - $totalBebanUsaha;
        $pendapatanLain  = $ringkasan['pendapatan_lain'];
        $bebanLain       = $ringkasan['beban_lain'];
        $labaSebelumPajak = $labaUsaha + $pendapatanLain - $bebanLain;

        $this->laporanData = $sections;
        $this->ringkasan   = [
            'total_pendapatan'    => $totalPendapatan,
            'total_hpp'           => $totalHPP,
            'laba_kotor'          => $labaKotor,
            'total_beban_usaha'   => $totalBebanUsaha,
            'laba_usaha'          => $labaUsaha,
            'pendapatan_lain'     => $pendapatanLain,
            'beban_lain'          => $bebanLain,
            'laba_sebelum_pajak'  => $labaSebelumPajak,

            // Flag untuk blade: tipe mana yang sudah melewati titik subtotal
            'ada_hpp'             => $totalHPP != 0 || $ringkasan['hpp'] != 0 || $ringkasan['beban_produksi'] != 0,
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

    private function getSaldoMap(): array
    {
        $rows = BukuBesar::where('tahun', $this->tahun)
            ->whereBetween('bulan', [$this->bulan_dari, $this->bulan_sampai])
            ->get(['no_akun', 'saldo']);

        $map = [];
        foreach ($rows as $row) {
            $kode        = $row->no_akun;
            $map[$kode]  = ($map[$kode] ?? 0) + (float) $row->saldo;
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
            1  => 'Januari',  2  => 'Februari', 3  => 'Maret',
            4  => 'April',    5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',     8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',  11 => 'November',  12 => 'Desember',
        ][$bulan] ?? '';
    }

    public function formatRupiah(float $nilai): string
    {
        return 'Rp ' . number_format(abs($nilai), 0, ',', '.');
    }

    /**
     * Apakah section ini adalah titik di mana subtotal perlu disisipkan?
     * Dipanggil dari blade setelah render setiap section.
     */
    public function getSubtotalSetelahTipe(string $tipe): ?array
    {
        $r = $this->ringkasan;

        return match($tipe) {
            // Setelah semua grup HPP/beban_produksi → tampilkan Laba Kotor
            'hpp', 'beban_produksi' => [
                'label' => 'Laba Kotor',
                'nilai' => $r['laba_kotor'],
                'style' => 'laba_kotor',
            ],
            // Setelah semua grup beban_usaha → tampilkan Laba Usaha
            'beban_usaha' => [
                'label' => 'Laba (Rugi) Usaha',
                'nilai' => $r['laba_usaha'],
                'style' => 'laba_usaha',
            ],
            // Setelah beban_lain → tampilkan Laba Sebelum Pajak
            'beban_lain' => [
                'label' => 'Laba (Rugi) Sebelum Pajak',
                'nilai' => $r['laba_sebelum_pajak'],
                'style' => 'laba_bersih',
            ],
            default => null,
        };
    }
}