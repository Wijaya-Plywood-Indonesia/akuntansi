<?php

namespace App\Filament\Resources\KelompokMappingAkuns\Tables;

use App\Filament\Resources\KelompokMappingAkuns\Schemas\KelompokMappingAkunForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;


class KelompokMappingAkunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_kelompok')
                    ->label('Kode Kelompok')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('nama_kelompok')
                    ->label('Nama Kelompok')
                    ->searchable(),

                TextColumn::make('kode_proses')
                    ->label('Proses')
                    ->badge()
                    ->default('Lintas Proses')
                    ->sortable(),

                TextColumn::make('mapping_akun_count')
                    ->label('Jml Akun')
                    ->counts('mappingAkun')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('dibuatOleh.name')
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('dieditOleh.name')
                    ->label('Diedit Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kode_proses')
                    ->label('Filter Proses')
                    ->options(KelompokMappingAkunForm::opsiProses())
                    ->placeholder('Semua Proses'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
