<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;

class JurnalUmum extends Page
{
    protected string $view = 'filament.pages.jurnal-umum';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
}
