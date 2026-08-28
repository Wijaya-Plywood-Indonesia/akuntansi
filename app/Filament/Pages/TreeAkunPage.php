<?php

namespace App\Filament\Pages;

use App\Models\IndukAkun;
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

    public function getViewData(): array
    {
        // Natural sort: pisahkan bagian sebelum & sesudah titik, cast ke angka
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

        return [
            'indukAkuns' => $indukAkuns,
        ];
    }
}
