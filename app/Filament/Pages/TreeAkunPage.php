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
    | BUKU PEMBANTU PIUTANG (per Pembeli)
    |--------------------------------------------------------------------------
    | Dipicu dari leaf row di tree COA untuk sub akun yang tipe_buku_pembantu-
    | nya = 'piutang'. Alurnya 2 level:
    |   Level 1 (openPiutangModal)  -> daftar pembeli + saldo piutang berjalan
    |   Level 2 (openPiutangDetail) -> kartu piutang 1 pembeli: tanggal,
    |                                  no dokumen (SJ/nota), debit, kredit,
    |                                  saldo berjalan.
    | Sumber data: jurnal_umums yang sudah ke-posting (no_akun = kode sub akun
    | terpilih). Baris lama yang belum punya id_pembeli tetap ikut kehitung,
    | dikelompokkan berdasarkan kolom teks `nama`.
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
     * Level 1: daftar pembeli yang punya transaksi di akun piutang terpilih,
     * beserta saldo berjalan (debit - kredit, karena piutang saldo normalnya
     * debit). Dikelompokkan by id_pembeli, fallback ke nama teks kalau
     * id_pembeli-nya NULL (data lama).
     *
     * @return \Illuminate\Support\Collection<int, object{key:string,nama:string,debit:float,kredit:float,saldo:float,jumlah_transaksi:int}>
     */
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

    public function getPiutangPembeliListProperty()
    {
        $subAkun = $this->selectedPiutangSubAkun;

        if (! $subAkun) {
            return collect();
        }

        $rows = JurnalUmum::query()
            ->with('pembeli:id,nama')
            ->where('no_akun', $subAkun->kode_sub_anak_akun)
            ->orderBy('tgl')
            ->get();

        return $rows
            ->groupBy(fn (JurnalUmum $r) => $r->id_pembeli ? 'id:'.$r->id_pembeli : 'nama:'.trim((string) $r->nama))
            ->map(function ($group, $key) {
                $first = $group->first();
                $debit = (float) $group->sum('debit');
                $kredit = (float) $group->sum('kredit');
                $tglTerakhir = $group->max('tgl');

                return (object) [
                    'key' => $key,
                    'nama' => $first->pembeli?->nama ?: ($first->nama ?: 'Tanpa Nama'),
                    'debit' => $debit,
                    'kredit' => $kredit,
                    'saldo' => $debit - $kredit,
                    // Jumlah NOTA unik (bukan jumlah baris jurnal — 1 nota
                    // bisa punya 2+ baris: tagihan + pelunasan/DP, jadi kalau
                    // dihitung dari baris jurnal angkanya bisa 2x lipat dari
                    // jumlah transaksi yang sebenarnya).
                    'jumlah_transaksi' => $group->pluck('no_dokumen')->filter()->unique()->count(),
                    'tgl_terakhir' => $tglTerakhir,
                    // Cuma dihitung kalau tanggal transaksi <= hari ini.
                    // Sebelumnya pakai abs() yang salah — tanggal transaksi
                    // yang MAJU (di masa depan, mis. data testing) malah ikut
                    // dihitung seolah "sudah lewat sekian hari", padahal
                    // seharusnya belum terjadi. Sekarang: transaksi di masa
                    // depan -> 0 (belum lewat), bukan dipaksa positif.
                    'hari_sejak_terakhir' => $this->hitungHariSejak($tglTerakhir),
                ];
            })
            ->sortByDesc('tgl_terakhir')
            ->values();
    }

    /**
     * Buka detail level 2 untuk 1 pembeli (dari baris yang diklik di level 1).
     */
    public function openPiutangDetail(string $pembeliKey): void
    {
        $this->selectedPembeliKey = $pembeliKey;
        $this->dispatch('open-modal', id: 'piutang-detail-modal');
    }

    /**
     * Data ringkas pembeli yang sedang dibuka di level 2 (nama + total saldo),
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
     * Level 2: rincian transaksi 1 pembeli pada akun piutang terpilih,
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

        if (str_starts_with($this->selectedPembeliKey, 'id:')) {
            $query->where('id_pembeli', (int) substr($this->selectedPembeliKey, 3));
        } else {
            $nama = substr($this->selectedPembeliKey, 5);
            $query->whereNull('id_pembeli')->where('nama', $nama);
        }

        $saldoBerjalan = 0.0;

        // Saldo berjalan WAJIB dihitung kronologis (lama -> baru), karena
        // nilainya kumulatif. Urutan TAMPIL yang dibalik (baru -> lama)
        // dilakukan setelah saldo_berjalan selesai dihitung, lewat reverse()
        // di akhir — supaya angkanya tetap benar tapi baris terbaru muncul
        // di atas.
        return $query->get()->map(function (JurnalUmum $r) use (&$saldoBerjalan) {
            $saldoBerjalan += $r->debit - $r->kredit;

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