<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Resources\Pembelians\PembeliansResource;
use App\Models\Pembelian;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPembelians extends ListRecords
{
    protected static string $resource = PembeliansResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Pembelian')
        ];
    }
}
