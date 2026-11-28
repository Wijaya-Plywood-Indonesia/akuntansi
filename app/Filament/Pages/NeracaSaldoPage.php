<?php

namespace App\Filament\Pages;

use App\Models\IndukAkun;
use App\Models\JurnalUmum;
use App\Models\SubAnakAkun;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use UnitEnum;

class NeracaSaldoPage extends Page
{
    use HasPageShield;

    protected static string|UnitEnum|null $navigationGroup = 'Jurnal & Akuntansi';

    protected string $view = 'filament.pages.neraca-saldo-page';

    protected static ?string $navigationLabel = 'Neraca Saldo';

    protected static ?string $title = 'Neraca Saldo';

    public string $tanggalAkhir = '';

    public bool $tampilkanSaldoNol = false;

    public ?int $selectedPiutangSubAkunId = null;

    public ?string $selectedPembeliKey = null;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->tanggalAkhir = now()->format('Y-m-d');
    }

    #[Computed]
    public function neracaSaldoData(): array
    {
        $tanggal = $this->tanggalAkhir ?: now()->format('Y-m-d');

        $nilaiExpr = "CASE
            WHEN LOWER(COALESCE(hit_kbk, '')) = 'b' THEN COALESCE(banyak, 0) * COALESCE(harga, 0)
            WHEN LOWER(COALESCE(hit_kbk, '')) = 'm' THEN COALESCE(m3, 0) * COALESCE(harga, 0)
            ELSE COALESCE(harga, 0)
        END";

        $jurnalSums = JurnalUmum::query()
            ->whereDate('tgl', '<=', $tanggal)
            ->select(
                'no_akun',
                DB::raw("COALESCE(SUM(CASE WHEN LOWER(COALESCE(map, '')) IN ('d', 'debit') THEN ({$nilaiExpr}) ELSE 0 END), 0) as total_debit"),
                DB::raw("COALESCE(SUM(CASE WHEN LOWER(COALESCE(map, '')) IN ('k', 'kredit') THEN ({$nilaiExpr}) ELSE 0 END), 0) as total_kredit")
            )
            ->groupBy('no_akun')
            ->get()
            ->keyBy('no_akun');

        $naturalSort = function ($query, string $column) {
            $query->orderByRaw("CAST(SUBSTRING_INDEX($column, '.', 1) AS UNSIGNED) asc")
                ->orderByRaw("CAST(SUBSTRING_INDEX($column, '.', -1) AS UNSIGNED) asc");
        };

        $indukAkuns = IndukAkun::with([
            'anakAkuns' => fn ($query) => $naturalSort($query, 'kode_anak_akun'),
            'anakAkuns.subAnakAkuns' => fn ($query) => $naturalSort($query, 'kode_sub_anak_akun'),
            'anakAkuns.children' => fn ($query) => $naturalSort($query, 'kode_anak_akun'),
            'anakAkuns.children.subAnakAkuns' => fn ($query) => $naturalSort($query, 'kode_sub_anak_akun'),
            'anakAkuns.children.children' => fn ($query) => $naturalSort($query, 'kode_anak_akun'),
            'anakAkuns.children.children.subAnakAkuns' => fn ($query) => $naturalSort($query, 'kode_sub_anak_akun'),
            'anakAkuns.children.children.children' => fn ($query) => $naturalSort($query, 'kode_anak_akun'),
            'anakAkuns.children.children.children.subAnakAkuns' => fn ($query) => $naturalSort($query, 'kode_sub_anak_akun'),
            'anakAkuns.children.children.children.children' => fn ($query) => $naturalSort($query, 'kode_anak_akun'),
            'anakAkuns.children.children.children.children.subAnakAkuns' => fn ($query) => $naturalSort($query, 'kode_sub_anak_akun'),
        ])
            ->where('status', 'aktif')
            ->orderBy('kode_induk_akun', 'asc')
            ->get();

        $walkAnak = function ($anakAkun) use (&$walkAnak, $jurnalSums): array {
            $debet = 0.0;
            $kredit = 0.0;
            $bpCount = 0;

            foreach ($anakAkun->subAnakAkuns as $sub) {
                $sum = $jurnalSums->get((string) $sub->kode_sub_anak_akun);
                $d = (float) ($sum->total_debit ?? 0);
                $k = (float) ($sum->total_kredit ?? 0);
                $net = $d - $k;

                $subDebet = $net > 0 ? $net : 0.0;
                $subKredit = $net < 0 ? abs($net) : 0.0;

                $sub->setAttribute('ns_debet', $subDebet);
                $sub->setAttribute('ns_kredit', $subKredit);

                if (in_array($sub->tipe_buku_pembantu, ['piutang', 'utang', 'campuran'], true)) {
                    $bpCount++;
                }

                $debet += $subDebet;
                $kredit += $subKredit;
            }

            foreach ($anakAkun->children as $child) {
                [$childDebet, $childKredit, $childBpCount] = $walkAnak($child);
                $debet += $childDebet;
                $kredit += $childKredit;
                $bpCount += $childBpCount;
            }

            $anakAkun->setAttribute('ns_debet', $debet);
            $anakAkun->setAttribute('ns_kredit', $kredit);
            $anakAkun->setAttribute('buku_pembantu_count', $bpCount);

            return [$debet, $kredit, $bpCount];
        };

        $totalDebet = 0.0;
        $totalKredit = 0.0;

        foreach ($indukAkuns as $induk) {
            $indukDebet = 0.0;
            $indukKredit = 0.0;
            $indukBpCount = 0;

            foreach ($induk->anakAkuns->whereNull('parent') as $anak) {
                [$anakDebet, $anakKredit, $anakBpCount] = $walkAnak($anak);
                $indukDebet += $anakDebet;
                $indukKredit += $anakKredit;
                $indukBpCount += $anakBpCount;
            }

            $induk->setAttribute('ns_debet', $indukDebet);
            $induk->setAttribute('ns_kredit', $indukKredit);
            $induk->setAttribute('buku_pembantu_count', $indukBpCount);

            $totalDebet += $indukDebet;
            $totalKredit += $indukKredit;
        }

        $selisih = abs($totalDebet - $totalKredit);

        return [
            'indukAkuns' => $indukAkuns,
            'total_debet' => $totalDebet,
            'total_kredit' => $totalKredit,
            'selisih' => $selisih,
            'is_balanced' => $selisih < 0.01,
            'tanggal' => $tanggal,
        ];
    }

    public function openPiutangModal(int $subAkunId): void
    {
        $this->selectedPiutangSubAkunId = $subAkunId;
        $this->selectedPembeliKey = null;
        $this->dispatch('open-modal', id: 'piutang-pembeli-modal');
    }

    public function getSelectedPiutangSubAkunProperty(): ?SubAnakAkun
    {
        if (! $this->selectedPiutangSubAkunId) {
            return null;
        }

        return SubAnakAkun::find($this->selectedPiutangSubAkunId);
    }

    private function hitungHariSejak($tanggal): ?int
    {
        if (! $tanggal) {
            return null;
        }

        $tgl = \Illuminate\Support\Carbon::parse($tanggal)->startOfDay();
        $hariIni = now()->startOfDay();

        return $tgl->lte($hariIni) ? $tgl->diffInDays($hariIni) : 0;
    }

    public function getSelectedBukuIsUtangProperty(): bool
    {
        return $this->selectedPiutangSubAkun?->tipe_buku_pembantu === 'utang';
    }

    public function getSelectedBukuIsCampuranProperty(): bool
    {
        return $this->selectedPiutangSubAkun?->tipe_buku_pembantu === 'campuran';
    }

    private function hitungSaldo(float $debit, float $kredit, bool $isUtang, bool $isCampuran, ?SubAnakAkun $subAkun): float
    {
        if ($isCampuran) {
            return strtolower((string) $subAkun?->saldo_normal) === 'debet' ? $debit - $kredit : $kredit - $debit;
        }

        return $isUtang ? $kredit - $debit : $debit - $kredit;
    }

    private function keyPihak(JurnalUmum $r, bool $isUtang, bool $isCampuran): string
    {
        if ($isCampuran) {
            if ($r->id_supplier) {
                return 'sup:'.$r->id_supplier;
            }
            if ($r->id_pembeli) {
                return 'pem:'.$r->id_pembeli;
            }

            return 'nama:'.trim((string) $r->nama);
        }

        $kolomId = $isUtang ? 'id_supplier' : 'id_pembeli';

        return $r->{$kolomId} ? 'id:'.$r->{$kolomId} : 'nama:'.trim((string) $r->nama);
    }

    public function getPiutangPembeliListProperty()
    {
        $subAkun = $this->selectedPiutangSubAkun;

        if (! $subAkun) {
            return collect();
        }

        $tanggal = $this->tanggalAkhir ?: now()->format('Y-m-d');

        $rows = JurnalUmum::query()
            ->with('pembeli:id,nama', 'supplier:id,nama')
            ->where('no_akun', $subAkun->kode_sub_anak_akun)
            ->whereDate('tgl', '<=', $tanggal)
            ->orderBy('tgl')
            ->get();

        $isUtang = $this->selectedBukuIsUtang;
        $isCampuran = $this->selectedBukuIsCampuran;

        return $rows
            ->groupBy(fn (JurnalUmum $r) => $this->keyPihak($r, $isUtang, $isCampuran))
            ->map(function ($group, $key) use ($isUtang, $isCampuran, $subAkun) {
                $first = $group->first();
                $debit = (float) $group->sum('debit');
                $kredit = (float) $group->sum('kredit');
                $tglTerakhir = $group->max('tgl');

                if ($isCampuran) {
                    $jenisPihak = str_starts_with($key, 'sup:') ? 'Supplier' : (str_starts_with($key, 'pem:') ? 'Pembeli' : null);
                    $namaPihak = match ($jenisPihak) {
                        'Supplier' => $first->supplier?->nama,
                        'Pembeli' => $first->pembeli?->nama,
                        default => null,
                    };
                } else {
                    $jenisPihak = null;
                    $namaPihak = $isUtang ? $first->supplier?->nama : $first->pembeli?->nama;
                }

                return (object) [
                    'key' => $key,
                    'jenis_pihak' => $jenisPihak,
                    'nama' => $namaPihak ?: ($first->nama ?: 'Tanpa Nama'),
                    'debit' => $debit,
                    'kredit' => $kredit,
                    'saldo' => $this->hitungSaldo($debit, $kredit, $isUtang, $isCampuran, $subAkun),
                    'jumlah_transaksi' => $group->pluck('no_dokumen')->filter()->unique()->count(),
                    'tgl_terakhir' => $tglTerakhir,
                    'hari_sejak_terakhir' => $this->hitungHariSejak($tglTerakhir),
                ];
            })
            ->sortByDesc('tgl_terakhir')
            ->values();
    }

    public function openPiutangDetail(string $pembeliKey): void
    {
        $this->selectedPembeliKey = $pembeliKey;
        $this->dispatch('open-modal', id: 'piutang-detail-modal');
    }

    public function getSelectedPembeliRingkasanProperty(): ?object
    {
        if (! $this->selectedPembeliKey) {
            return null;
        }

        return $this->piutangPembeliList->firstWhere('key', $this->selectedPembeliKey);
    }

    public function getPiutangDetailTransaksiProperty()
    {
        $subAkun = $this->selectedPiutangSubAkun;

        if (! $subAkun || ! $this->selectedPembeliKey) {
            return collect();
        }

        $tanggal = $this->tanggalAkhir ?: now()->format('Y-m-d');

        $query = JurnalUmum::query()
            ->where('no_akun', $subAkun->kode_sub_anak_akun)
            ->whereDate('tgl', '<=', $tanggal)
            ->orderBy('tgl')
            ->orderBy('id');

        $isUtang = $this->selectedBukuIsUtang;
        $isCampuran = $this->selectedBukuIsCampuran;
        $kolomId = $isUtang ? 'id_supplier' : 'id_pembeli';
        $key = $this->selectedPembeliKey;

        if ($isCampuran) {
            if (str_starts_with($key, 'sup:')) {
                $query->where('id_supplier', (int) substr($key, 4));
            } elseif (str_starts_with($key, 'pem:')) {
                $query->whereNull('id_supplier')->where('id_pembeli', (int) substr($key, 4));
            } else {
                $query->whereNull('id_supplier')->whereNull('id_pembeli')->where('nama', substr($key, 5));
            }
        } elseif (str_starts_with($key, 'id:')) {
            $query->where($kolomId, (int) substr($key, 3));
        } else {
            $query->whereNull($kolomId)->where('nama', substr($key, 5));
        }

        $saldoBerjalan = 0.0;

        return $query->get()->map(function (JurnalUmum $r) use (&$saldoBerjalan, $isUtang, $isCampuran, $subAkun) {
            $saldoBerjalan += $this->hitungSaldo((float) $r->debit, (float) $r->kredit, $isUtang, $isCampuran, $subAkun);

            return (object) [
                'tgl' => $r->tgl,
                'no_dokumen' => $r->no_dokumen,
                'keterangan' => $r->keterangan,
                'debit' => (float) $r->debit,
                'kredit' => (float) $r->kredit,
                'saldo_berjalan' => $saldoBerjalan,
            ];
        })->reverse()->values();
    }
}
