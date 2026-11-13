<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrdersResource;
use App\Models\PurchaseOrder;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrdersResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cetak_pdf')
                ->label('Cetak PDF')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn () => route('purchase-order.cetak-pdf', $this->record))
                ->openUrlInNewTab(),

            Action::make('ubah_status')
                ->label('Ubah Status')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('status')
                        ->label('Status Baru')
                        ->options(PurchaseOrder::labelStatus())
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->update(['status' => $data['status']]);
                    Notification::make()
                        ->success()
                        ->title('Status diperbarui')
                        ->body('Status PO berhasil diubah ke: ' . PurchaseOrder::labelStatus()[$data['status']])
                        ->send();
                    $this->refreshFormData(['status']);
                }),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}

