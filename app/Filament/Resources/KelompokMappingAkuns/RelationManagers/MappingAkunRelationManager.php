<?php

namespace App\Filament\Resources\KelompokMappingAkuns\RelationManagers;

use App\Models\SubAnakAkun;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MappingAkunRelationManager extends RelationManager
{
    protected static string $relationship = 'mappingAkun';
    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sub_anak_akun_id')
                    ->label('Akun')
                    ->required()
                    ->relationship('subAnakAkun', 'nama_sub_anak_akun')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->kode_sub_anak_akun} — {$record->nama_sub_anak_akun}")
                    ->searchable(['kode_sub_anak_akun', 'nama_sub_anak_akun'])
                    ->preload()
                    ->columnSpanFull(),

                Select::make('posisi_jurnal')
                    ->label('Posisi Jurnal')
                    ->options([
                        'debet' => 'Debet',
                        'kredit' => 'Kredit',
                        'keduanya' => 'Keduanya',
                    ])
                    ->required(),

                TextInput::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->default(1)
                    ->minValue(1),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'aktif' => 'Aktif',
                        'nonaktif' => 'Nonaktif',
                    ])
                    ->default('aktif')
                    ->required(),

                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->nullable()
                    ->columnSpanFull(),
            ])
            ->columns(2);
        ;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('kode_akun')
            ->defaultSort('urutan', 'asc')
            ->columns([
                TextColumn::make('urutan')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),

                TextColumn::make('subAnakAkun.nama_sub_anak_akun')
                    ->label('Nama Akun'),

                TextColumn::make('subAnakAkun.nama_sub_anak_akun')
                    ->label('Nama Akun')
                    ->searchable(),

                TextColumn::make('posisi_jurnal')
                    ->label('Posisi')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'debet' => 'info',
                        'kredit' => 'success',
                        'keduanya' => 'warning',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'aktif' => 'success',
                        'nonaktif' => 'danger',
                    }),

                TextColumn::make('dibuatOleh.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('dieditOleh.name')
                    ->label('Diedit Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                //  AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                // DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
