<?php

namespace App\Filament\Pages;

use App\Models\IndukAkun;

use Filament\Pages\Page;
use BackedEnum;

class TreeAkunPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.tree-akun-page';
    protected static ?string $navigationLabel = 'Chart of Accounts';
    protected static ?string $title = 'Chart of Accounts';

    public function getViewData(): array
    {
        $indukAkuns = IndukAkun::with([
            'anakAkuns.children.children.subAnakAkuns',
            'anakAkuns.children.children.children.subAnakAkuns',
            'anakAkuns.children.subAnakAkuns',
            'anakAkuns.subAnakAkuns',
        ])
            ->where('status', 'aktif')
            ->orderBy('kode_induk_akun')
            ->get();

        return [
            'indukAkuns' => $indukAkuns,
        ];
    }
}