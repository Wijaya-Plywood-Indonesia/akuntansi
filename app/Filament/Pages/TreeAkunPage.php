<?php

namespace App\Filament\Pages;

use App\Models\Barang;
use App\Models\IndukAkun;
use App\Models\JurnalUmum;
use App\Models\SubAnakAkun;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class TreeAkunPage extends Page
{
    use HasPageShield;

    protected static string|UnitEnum|null $navigationGroup = 'Jurnal & Akuntansi';

    protected string $view = 'filament.pages.tree-akun-page';

    protected static ?string $navigationLabel = 'Chart of Accounts';

    protected static ?string $title = 'Chart of Accounts';

    // ===== State untuk modal barang =====
    public ?int $selectedSubAkunId = null;

    // ===== State untuk modal buku pembantu piutang =====
    public ?int $selectedPiutangSubAkunId = null;

    // Key pembeli yang dipilih di level 1, dibuka ke detail level 2.
    // Formatnya "id:<id_pembeli>" untuk pembeli bermaster, atau
    // "nama:<nama>" untuk baris jurnal lama yang belum ada id_pembeli-nya.
    // Untuk akun bertipe 'campuran': "sup:<id_supplier>" / "pem:<id_pembeli>".
    public ?string $selectedPembeliKey = null;

    public function getViewData(): array
    {
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
            'allAnakAkuns',
        ])
            ->where('status', 'aktif')
            ->orderBy('kode_induk_akun', 'asc')
            ->get();

        $this->attachBarangCounts($indukAkuns);

        return [
            'indukAkuns' => $indukAkuns,
        ];
    }

    /**
     * Hitung jumlah barang yang terhubung ke tiap sub anak akun
     * HANYA lewat kolom id_sub_anak_akun (akun utama barang).
     * Kolom akun_pendapatan_id & akun_hpp_id sengaja TIDAK dihitung
     * di sini, karena badge di tree COA cuma mau nunjukin "akun ini
     * dipakai sebagai akun utama oleh berapa barang".
     *
     * Ditempel sebagai atribut dinamis:
     *   - $subAnakAkun->barang_count        -> jumlah barang di sub akun itu sendiri
     *   - $anakAkun->barang_count_total     -> total barang di anak akun ini + semua turunannya
     *
     * Dihitung SEKALI di sini (bukan per-node di blade) supaya tidak
     * terjadi query berulang (N+1) saat tree dirender.
     */
    protected function attachBarangCounts($indukAkuns): void
    {
        // subAkunId => jumlah barang yang id_sub_anak_akun-nya = subAkunId itu
        $counts = Barang::query()
            ->whereNotNull('id_sub_anak_akun')
            ->selectRaw('id_sub_anak_akun, COUNT(*) as total')
            ->groupBy('id_sub_anak_akun')
            ->pluck('total', 'id_sub_anak_akun')
            ->all();

        // Rekursif: jalan ke setiap anak akun & children-nya, tempel count.
        $walk = function ($anakAkun) use (&$walk, $counts) {
            $total = 0;

            foreach ($anakAkun->subAnakAkuns as $sub) {
                $c = $counts[$sub->id] ?? 0;
                $sub->setAttribute('barang_count', $c);
                $total += $c;
            }

            foreach ($anakAkun->children as $child) {
                $total += $walk($child);
            }

            $anakAkun->setAttribute('barang_count_total', $total);

            return $total;
        };

        foreach ($indukAkuns as $induk) {
            $indukTotal = 0;

            foreach ($induk->anakAkuns->whereNull('parent') as $anak) {
                $indukTotal += $walk($anak);
            }

            $induk->setAttribute('barang_count_total', $indukTotal);
        }
    }

    /**
     * Dipanggil dari leaf row (sub anak akun) via wire:click.
     */
    public function openBarangModal(int $subAkunId): void
    {
        $this->selectedSubAkunId = $subAkunId;
        $this->dispatch('open-modal', id: 'barang-sub-akun-modal');
    }

    /**
     * Sub akun yang lagi dipilih (untuk header modal).
     */
    public function getSelectedSubAkunProperty(): ?SubAnakAkun
    {
        if (! $this->selectedSubAkunId) {
            return null;
        }

        return SubAnakAkun::find($this->selectedSubAkunId);
    }

    /**
     * Barang yang terhubung ke sub akun terpilih, dikelompokkan
     * berdasarkan jenis relasinya (akun utama / pendapatan / HPP).
     */
    public function getBarangTerkaitProperty(): array
    {
        if (! $this->selectedSubAkunId) {
            return [
                'utama' => collect(),
                'pendapatan' => collect(),
                'hpp' => collect(),
            ];
        }

        $id = $this->selectedSubAkunId;

        return [
            'utama' => Barang::with(['kategori', 'satuan'])
                ->where('id_sub_anak_akun', $id)
                ->get(),
            'pendapatan' => Barang::with(['kategori', 'satuan'])
                ->where('akun_pendapatan_id', $id)
                ->get(),
            'hpp' => Barang::with(['kategori', 'satuan'])
                ->where('akun_hpp_id', $id)
                ->get(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | BUKU PEMBANTU PIUTANG / UTANG / CAMPURAN
    |--------------------------------------------------------------------------
    | Dipicu dari leaf row di tree COA untuk sub akun yang tipe_buku_pembantu-
    | nya = 'piutang', 'utang' atau 'campuran'. Alurnya 2 level:
    |   Level 1 (openPiutangModal)  -> daftar pihak + saldo berjalan
    |   Level 2 (openPiutangDetail) -> kartu 1 pihak: tanggal,
    |                                  no dokumen (SJ/nota), debit, kredit,
    |                                  saldo berjalan.
    | Sumber data: jurnal_umums yang sudah ke-posting (no_akun = kode sub akun
    | terpilih). Baris lama yang belum punya id_pembeli/id_supplier tetap ikut
    | kehitung, dikelompokkan berdasarkan kolom teks `nama`.
    |
    | Tipe 'campuran' (mis. 2228.1 Titipan): pihaknya bisa Supplier ATAU
    | Pembeli, dibedakan lewat id_supplier / id_pembeli di jurnal.
    */

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

    /**
     * Hitung "hari sejak" dari sebuah tanggal ke hari ini, HANYA kalau
     * tanggal itu sudah lewat (<= hari ini). Tanggal yang masih di masa
     * depan sengaja dikembalikan 0, bukan dipaksa jadi angka positif lewat
     * abs() — karena transaksi yang belum terjadi bukan "sudah sekian hari
     * yang lalu".
     */
    private function hitungHariSejak($tanggal): ?int
    {
        if (! $tanggal) {
            return null;
        }

        $tgl = \Illuminate\Support\Carbon::parse($tanggal)->startOfDay();
        $hariIni = now()->startOfDay();

        return $tgl->lte($hariIni) ? $tgl->diffInDays($hariIni) : 0;
    }

    /** True kalau sub akun yang dibuka bertipe 'utang' (per Supplier), bukan 'piutang' (per Pembeli). */
    public function getSelectedBukuIsUtangProperty(): bool
    {
        return $this->selectedPiutangSubAkun?->tipe_buku_pembantu === 'utang';
    }

    /** True kalau sub akun bertipe 'campuran' (pihaknya bisa Supplier ATAU Pembeli, mis. 2228.1 Titipan). */
    public function getSelectedBukuIsCampuranProperty(): bool
    {
        return $this->selectedPiutangSubAkun?->tipe_buku_pembantu === 'campuran';
    }

    /**
     * Hitung saldo satu baris/kelompok.
     * Piutang: saldo normal debit. Utang: saldo normal kredit.
     * Campuran: ikut saldo normal akun (Titipan = kredit).
     */
    private function hitungSaldo(float $debit, float $kredit, bool $isUtang, bool $isCampuran, ?SubAnakAkun $subAkun): float
    {
        if ($isCampuran) {
            return strtolower((string) $subAkun?->saldo_normal) === 'debet' ? $debit - $kredit : $kredit - $debit;
        }

        return $isUtang ? $kredit - $debit : $debit - $kredit;
    }

    /**
     * Key pengelompokan baris jurnal. Untuk campuran dibedakan prefix
     * sup:/pem: karena id supplier & id pembeli bisa kembar.
     */
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

    /**
     * Level 1: daftar pihak (pembeli/supplier) yang punya transaksi di akun
     * terpilih, beserta saldo berjalan.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getPiutangPembeliListProperty()
    {
        $subAkun = $this->selectedPiutangSubAkun;

        if (! $subAkun) {
            return collect();
        }

        $rows = JurnalUmum::query()
            ->with('pembeli:id,nama', 'supplier:id,nama')
            ->where('no_akun', $subAkun->kode_sub_anak_akun)
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
                    // Piutang: saldo normal debit. Utang: saldo normal kredit.
                    // Campuran: ikut saldo normal akun (Titipan = kredit).
                    'saldo' => $this->hitungSaldo($debit, $kredit, $isUtang, $isCampuran, $subAkun),
                    // Jumlah NOTA unik (bukan jumlah baris jurnal — 1 nota
                    // bisa punya 2+ baris: tagihan + pelunasan/DP, jadi kalau
                    // dihitung dari baris jurnal angkanya bisa 2x lipat dari
                    // jumlah transaksi yang sebenarnya).
                    'jumlah_transaksi' => $group->pluck('no_dokumen')->filter()->unique()->count(),
                    'tgl_terakhir' => $tglTerakhir,
                    // Cuma dihitung kalau tanggal transaksi <= hari ini.
                    // Transaksi di masa depan -> 0 (belum lewat), bukan
                    // dipaksa positif.
                    'hari_sejak_terakhir' => $this->hitungHariSejak($tglTerakhir),
                ];
            })
            ->sortByDesc('tgl_terakhir')
            ->values();
    }

    /**
     * Buka detail level 2 untuk 1 pihak (dari baris yang diklik di level 1).
     */
    public function openPiutangDetail(string $pembeliKey): void
    {
        $this->selectedPembeliKey = $pembeliKey;
        $this->dispatch('open-modal', id: 'piutang-detail-modal');
    }

    /**
     * Data ringkas pihak yang sedang dibuka di level 2 (nama + total saldo),
     * diambil dari hasil level 1 supaya tidak query ulang.
     */
    public function getSelectedPembeliRingkasanProperty(): ?object
    {
        if (! $this->selectedPembeliKey) {
            return null;
        }

        return $this->piutangPembeliList->firstWhere('key', $this->selectedPembeliKey);
    }

    /**
     * Level 2: rincian transaksi 1 pihak pada akun terpilih,
     * diurutkan tanggal, dengan saldo berjalan (running balance).
     *
     * @return \Illuminate\Support\Collection<int, object{tgl:mixed,no_dokumen:?string,keterangan:?string,debit:float,kredit:float,saldo_berjalan:float}>
     */
    public function getPiutangDetailTransaksiProperty()
    {
        $subAkun = $this->selectedPiutangSubAkun;

        if (! $subAkun || ! $this->selectedPembeliKey) {
            return collect();
        }

        $query = JurnalUmum::query()
            ->where('no_akun', $subAkun->kode_sub_anak_akun)
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

        // Saldo berjalan WAJIB dihitung kronologis (lama -> baru), karena
        // nilainya kumulatif. Urutan TAMPIL yang dibalik (baru -> lama)
        // dilakukan setelah saldo_berjalan selesai dihitung, lewat reverse()
        // di akhir — supaya angkanya tetap benar tapi baris terbaru muncul
        // di atas.
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