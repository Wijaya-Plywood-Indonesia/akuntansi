<?php

namespace App\Filament\Resources\KelompokMappingAkuns\Pages;

use App\Filament\Resources\KelompokMappingAkuns\KelompokMappingAkunResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKelompokMappingAkun extends ViewRecord
{
    protected static string $resource = KelompokMappingAkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
