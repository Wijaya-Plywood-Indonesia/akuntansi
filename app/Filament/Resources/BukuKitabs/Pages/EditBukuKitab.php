<?php

namespace App\Filament\Resources\BukuKitabs\Pages;

use App\Filament\Resources\BukuKitabs\BukuKitabResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBukuKitab extends EditRecord
{
    protected static string $resource = BukuKitabResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
