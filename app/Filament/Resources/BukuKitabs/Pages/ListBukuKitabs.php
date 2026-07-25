<?php

namespace App\Filament\Resources\BukuKitabs\Pages;

use App\Filament\Resources\BukuKitabs\BukuKitabResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBukuKitabs extends ListRecords
{
    protected static string $resource = BukuKitabResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
