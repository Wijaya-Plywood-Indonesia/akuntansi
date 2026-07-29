<?php

namespace App\Filament\Resources\BukuKitabs\Tables;

use App\Models\BukuKitab;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BukuKitabsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')
                    ->label('Kode')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kategori')
                    ->label('Kategori')
                    ->badge()
                    ->sortable(),
                TextColumn::make('akun_detail_count')
                    ->label('Jumlah Baris Akun')
                    ->counts('akunDetail')
                    ->alignCenter(),
                TextColumn::make('keterangan')
                    ->label('Catatan')
                    ->limit(50)
                    ->tooltip(fn($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('kategori')
                    ->options(fn() => BukuKitab::whereNotNull('kategori')->distinct()->pluck('kategori', 'kategori')->toArray()),
            ])
            ->defaultSort('kode');
    }
}