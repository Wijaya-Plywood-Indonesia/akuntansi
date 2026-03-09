<?php

namespace App\Filament\Pages;

use App\Models\AkunGroup;
use App\Models\AnakAkun;
use App\Models\SubAnakAkun;
use App\Models\JurnalUmum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Carbon\Carbon;
use BackedEnum;
use UnitEnum;

class LabaRugi extends Page
{
    use HasPageShield;
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected static ?string $title = 'Laporan Laba Rugi';

    protected string $view = 'filament.pages.laba-rugi';

    public ?int $tahun = null;
    public ?int $bulan_dari = null;
    public ?int $bulan_sampai = null;
    public array $data = [];

    public array $laporanData = [];
    public array $bulanList = [];
    public array $ringkasanPerBulan = [];
    public bool $sudahFilter = false;

    public function mount(): void
    {
        $this->tahun = now()->year;
        $this->bulan_dari = now()->month;
        $this->bulan_sampai = now()->month;

        $this->schema->fill([
            'tahun' => $this->tahun,
            'bulan_dari' => $this->bulan_dari,
            'bulan_sampai' => $this->bulan_sampai,
        ]);

        $this->generateLaporan();
    }

    public function schema(Schema $schema): Schema
    {
        $bulanOptions = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
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
                    Select::make('tahun')->options($tahunOptions)->required()->live(),
                    Select::make('bulan_dari')->options($bulanOptions)->required()->live(),
                    Select::make('bulan_sampai')->options($bulanOptions)->required()->live(),
                ]),
            ])
            ->statePath('data');
    }

    public function filter(): void
    {
        $this->tahun = (int) $this->data['tahun'];
        $this->bulan_dari = (int) $this->data['bulan_dari'];
        $this->bulan_sampai = (int) $this->data['bulan_sampai'];

        $this->generateLaporan();
    }

    public function generateLaporan(): void
    {
        $tahun = $this->tahun;
        $bulanDari = $this->bulan_dari;
        $bulanSampai = $this->bulan_sampai;

        $bulanList = [];
        for ($b = $bulanDari; $b <= $bulanSampai; $b++) {
            $bulanList[] = $b;
        }
        $this->bulanList = $bulanList;

        $saldoPerBulan = $this->getSaldoMapPerBulan();

        $root = AkunGroup::whereNull('parent_id')
            ->whereRaw('LOWER(nama) LIKE ?', ['%laba rugi%'])
            ->first();

        if (!$root) {
            $this->laporanData = [];
            $this->ringkasanPerBulan = [];
            $this->sudahFilter = true;
            return;
        }

        $groups = AkunGroup::where('parent_id', $root->id)
            ->visible()
            ->ordered()
            ->with(['childrenRecursive.anakAkuns.subAnakAkuns', 'anakAkuns.subAnakAkuns'])
            ->get();

        $sections = [];
        foreach ($groups as $group) {
            $sections[] = $this->buildGroupNode($group, $saldoPerBulan, $bulanList);
        }

        $ringkasan = [];

        foreach ($bulanList as $bulan) {

            $r = [
                'pendapatan' => 0,
                'retur_potongan' => 0,
                'hpp' => 0,
                'beban_produksi' => 0,
                'beban_usaha' => 0,
                'pendapatan_lain' => 0,
                'beban_lain' => 0,
            ];

            foreach ($sections as $section) {
                $tipe = $section['tipe'];
                if (isset($r[$tipe])) {
                    $r[$tipe] += $section['nilai_per_bulan'][$bulan] ?? 0;
                }
            }

            $penjualanBersih = $r['pendapatan'] - $r['retur_potongan'];
            $totalHPP = $r['hpp'] + $r['beban_produksi'];
            $labaKotor = $penjualanBersih - $totalHPP;
            $labaUsaha = $labaKotor - $r['beban_usaha'];
            $labaSblPajak = $labaUsaha + $r['pendapatan_lain'] - $r['beban_lain'];

            $ringkasan[$bulan] = [
                'total_pendapatan' => $r['pendapatan'],
                'penjualan_bersih' => $penjualanBersih,
                'total_hpp' => $totalHPP,
                'laba_kotor' => $labaKotor,
                'laba_usaha' => $labaUsaha,
                'laba_sebelum_pajak' => $labaSblPajak,
            ];
        }

        $this->laporanData = $sections;
        $this->ringkasanPerBulan = $ringkasan;
        $this->sudahFilter = true;
    }

    private function getSaldoMapPerBulan(): array
    {
        $saldoPerBulan = [];

        $saldoNormalMap =
            AnakAkun::pluck('saldo_normal', 'kode_anak_akun')->toArray()
            + SubAnakAkun::pluck('saldo_normal', 'kode_sub_anak_akun')->toArray();

        for ($bulan = $this->bulan_dari; $bulan <= $this->bulan_sampai; $bulan++) {

            $start = Carbon::create($this->tahun, $bulan, 1)->startOfMonth();
            $end = Carbon::create($this->tahun, $bulan, 1)->endOfMonth();

            $map = [];

            $jurnals = JurnalUmum::whereBetween('tgl', [$start, $end])->get();

            foreach ($jurnals as $jurnal) {

                $kode = $jurnal->no_akun;
                $nilai = ($jurnal->banyak ?? 0) * ($jurnal->harga ?? 0);

                $saldoNormal = strtolower($saldoNormalMap[$kode] ?? 'debit');
                $isDebit = strtolower($jurnal->map) === 'd';

                if ($saldoNormal === 'kredit') {
                    $map[$kode] = ($map[$kode] ?? 0) + ($isDebit ? -$nilai : $nilai);
                } else {
                    $map[$kode] = ($map[$kode] ?? 0) + ($isDebit ? $nilai : -$nilai);
                }
            }

            $saldoPerBulan[$bulan] = $map;
        }

        return $saldoPerBulan;
    }

    private function buildGroupNode(AkunGroup $group, array $saldoPerBulan, array $bulanList): array
    {
        $children = [];
        $nilaiPerBulan = array_fill_keys($bulanList, 0);

        foreach ($group->children as $child) {
            $node = $this->buildGroupNode($child, $saldoPerBulan, $bulanList);
            $children[] = $node;
            foreach ($bulanList as $b) {
                $nilaiPerBulan[$b] += $node['nilai_per_bulan'][$b] ?? 0;
            }
        }

        foreach ($group->anakAkuns as $anak) {
            $node = $this->buildAnakAkunNode($anak, $saldoPerBulan, $bulanList);
            $children[] = $node;
            foreach ($bulanList as $b) {
                $nilaiPerBulan[$b] += $node['nilai_per_bulan'][$b] ?? 0;
            }
        }

        return [
            'type' => 'group',
            'nama' => $group->nama,
            'tipe' => $group->tipe ?? 'lainnya',
            'hidden' => $group->hidden,
            'children' => $children,
            'nilai_per_bulan' => $nilaiPerBulan,
        ];
    }

    private function buildAnakAkunNode(AnakAkun $anak, array $saldoPerBulan, array $bulanList): array
    {
        $children = [];
        $nilaiPerBulan = array_fill_keys($bulanList, 0);

        foreach ($anak->subAnakAkuns as $sub) {
            $node = $this->buildSubAnakAkunNode($sub, $saldoPerBulan, $bulanList);
            $children[] = $node;
            foreach ($bulanList as $b) {
                $nilaiPerBulan[$b] += $node['nilai_per_bulan'][$b] ?? 0;
            }
        }

        foreach ($bulanList as $b) {
            $nilaiPerBulan[$b] += $saldoPerBulan[$b][$anak->kode_anak_akun] ?? 0;
        }

        return [
            'type' => 'anak_akun',
            'kode' => $anak->kode_anak_akun,
            'nama' => $anak->nama_anak_akun,
            'children' => $children,
            'nilai_per_bulan' => $nilaiPerBulan,
        ];
    }

    private function buildSubAnakAkunNode(SubAnakAkun $sub, array $saldoPerBulan, array $bulanList): array
    {
        $nilaiPerBulan = [];
        foreach ($bulanList as $b) {
            $nilaiPerBulan[$b] = $saldoPerBulan[$b][$sub->kode_sub_anak_akun] ?? 0;
        }

        return [
            'type' => 'sub_anak_akun',
            'kode' => $sub->kode_sub_anak_akun,
            'nama' => $sub->nama_sub_anak_akun,
            'children' => [],
            'nilai_per_bulan' => $nilaiPerBulan,
        ];
    }

    public function getNamaBulan(int $bulan): string
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
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