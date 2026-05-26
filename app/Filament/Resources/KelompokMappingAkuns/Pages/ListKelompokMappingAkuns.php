<?php

namespace App\Filament\Resources\KelompokMappingAkuns\Pages;

use App\Filament\Resources\KelompokMappingAkuns\KelompokMappingAkunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKelompokMappingAkuns extends ListRecords
{
    protected static string $resource = KelompokMappingAkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
