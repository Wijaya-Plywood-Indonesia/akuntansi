<?php

namespace App\Filament\Resources\KelompokMappingAkuns\Pages;

use App\Filament\Resources\KelompokMappingAkuns\KelompokMappingAkunResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditKelompokMappingAkun extends EditRecord
{
    protected static string $resource = KelompokMappingAkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
