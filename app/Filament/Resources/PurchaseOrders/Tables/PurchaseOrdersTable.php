<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Models\PurchaseOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_po')
                    ->label('Nomor PO')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('tanggal_jatuh_tempo')
                    ->label('Jatuh Tempo')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('supplier_name')
                    ->label('Supplier')
                    ->searchable(),

                TextColumn::make('grand_total')
                    ->label('Grand Total')
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => PurchaseOrder::labelStatus()[$state] ?? $state)
                    ->color(fn ($state) => PurchaseOrder::warnaBadgeStatus()[$state] ?? 'gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PurchaseOrder::labelStatus()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('cetak_pdf')
                    ->label('Cetak PDF')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn ($record) => route('purchase-order.cetak-pdf', $record))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function ($record, DeleteAction $action) {
                        $adaItem = $record->items()->exists();
                        if ($adaItem) {
                            Notification::make()
                                ->danger()
                                ->title('Data Gagal Dihapus!')
                                ->body("PO {$record->nomor_po} tidak dapat dihapus karena masih memiliki item barang. Hapus item terlebih dahulu.")
                                ->persistent()
                                ->send();
                            $action->halt();
                        }
                        $record->delete();
                        Notification::make()
                            ->success()
                            ->title('Berhasil Dihapus')
                            ->body("PO {$record->nomor_po} telah dihapus.")
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn() => filament()->auth()->user()?->hasRole('super_admin') ?? false),
                ]),
            ]);
    }
}

