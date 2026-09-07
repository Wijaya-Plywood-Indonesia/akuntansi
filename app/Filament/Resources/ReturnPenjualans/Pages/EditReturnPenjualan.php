<?php

namespace App\Filament\Resources\ReturnPenjualans\Pages;

use App\Filament\Resources\ReturnPenjualans\ReturnPenjualanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReturnPenjualan extends EditRecord
{
    protected static string $resource = ReturnPenjualanResource::class;

    protected static ?string $title = 'Detail & Edit Retur Penjualan';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn() => auth()->user()?->hasRole("super_admin")),
        ];
    }
}
