<?php

namespace App\Filament\Pages;

use App\Models\Barang;
use App\Models\IndukAkun;
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
}
