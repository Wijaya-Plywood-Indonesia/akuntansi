<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\PurchaseOrder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi PO')
                    ->schema([
                        TextEntry::make('nomor_po')
                            ->label('Nomor PO')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('tanggal')
                            ->label('Tanggal PO')
                            ->date('d M Y'),

                        TextEntry::make('tanggal_jatuh_tempo')
                            ->label('Jatuh Tempo')
                            ->date('d M Y')
                            ->placeholder('-'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => PurchaseOrder::labelStatus()[$state] ?? $state)
                            ->color(fn ($state) => PurchaseOrder::warnaBadgeStatus()[$state] ?? 'gray'),

                        TextEntry::make('supplier_name')
                            ->label('Supplier'),

                        TextEntry::make('supplier_phone')
                            ->label('Telepon')
                            ->placeholder('-'),

                        TextEntry::make('supplier_address')
                            ->label('Alamat')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('createdBy.name')
                            ->label('Dibuat Oleh'),

                        TextEntry::make('catatan')
                            ->label('Catatan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Nominal')
                    ->schema([
                        TextEntry::make('sub_total')
                            ->label('Sub Total')
                            ->money('IDR'),

                        TextEntry::make('total_diskon')
                            ->label('Total Diskon')
                            ->money('IDR')
                            ->color('danger'),

                        TextEntry::make('total_ppn')
                            ->label('Total PPN')
                            ->money('IDR'),

                        TextEntry::make('ongkir')
                            ->label('Ongkir')
                            ->money('IDR'),

                        TextEntry::make('biaya_lain')
                            ->label('Biaya Lain')
                            ->money('IDR'),

                        TextEntry::make('grand_total')
                            ->label('Grand Total')
                            ->money('IDR')
                            ->weight('bold')
                            ->color('success')
                            ->badge(),
                    ])
                    ->columns(3),
            ]);
    }
}
