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
        $indukAkuns = IndukAkun::with([
            // Urutkan anakAkuns berdasarkan kode
            'anakAkuns' => function ($query) {
                $query->orderBy('kode_anak_akun', 'asc');
            },
            // Urutkan subAnakAkuns berdasarkan kode (1101-0 -> 1101-1 -> 1101-3)
            'anakAkuns.subAnakAkuns' => function ($query) {
                $query->orderBy('kode_sub_anak_akun', 'asc');
            },
            'anakAkuns.children' => function ($query) {
                $query->orderBy('kode_anak_akun', 'asc');
            },
            'anakAkuns.children.subAnakAkuns' => function ($query) {
                $query->orderBy('kode_sub_anak_akun', 'asc');
            },
            'anakAkuns.children.children' => function ($query) {
                $query->orderBy('kode_anak_akun', 'asc');
            },
            'anakAkuns.children.children.subAnakAkuns' => function ($query) {
                $query->orderBy('kode_sub_anak_akun', 'asc');
            },
            'anakAkuns.children.children.children' => function ($query) {
                $query->orderBy('kode_anak_akun', 'asc');
            },
            'anakAkuns.children.children.children.subAnakAkuns' => function ($query) {
                $query->orderBy('kode_sub_anak_akun', 'asc');
            },
            'anakAkuns.children.children.children.children' => function ($query) {
                $query->orderBy('kode_anak_akun', 'asc');
            },
            'anakAkuns.children.children.children.children.subAnakAkuns' => function ($query) {
                $query->orderBy('kode_sub_anak_akun', 'asc');
            },
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
