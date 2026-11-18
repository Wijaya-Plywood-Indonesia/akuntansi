<?php

namespace App\Filament\Resources\Penjualans\Pages;

use App\Filament\Resources\Penjualans\PenjualanResource;
use App\Models\Pembeli;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPenjualan extends EditRecord
{
    protected static string $resource = PenjualanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Form edit hanya punya kolom nama_customer, bukan pembeli_id. Samakan ID
     * dengan nama yang disimpan supaya Buku Pembantu Piutang tidak salah orang.
     * Catatan: jurnal yang sudah diposting tidak ikut berubah otomatis.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $pembeli = Pembeli::resolveUntukNota(
            $this->record->pembeli_id,
            $data['nama_customer'] ?? null,
            $data['alamat'] ?? null
        );

        $data['nama_customer'] = trim($data['nama_customer']);
        $data['pembeli_id'] = $pembeli->id;

        return $data;
    }
}