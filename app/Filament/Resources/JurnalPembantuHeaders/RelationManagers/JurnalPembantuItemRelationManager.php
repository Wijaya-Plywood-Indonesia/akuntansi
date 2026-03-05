<?php

namespace App\Filament\Resources\JurnalPembantuHeaders\RelationManagers;

use App\Models\JurnalPembantuItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JurnalPembantuItemRelationManager extends RelationManager
{
    protected static string $relationship = 'items'; // pastikan di Header relasinya: items()

    protected static ?string $title = 'Detail Item';
    public function isReadOnly(): bool
{
    return false;
}

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                    TextInput::make('urut')
                        ->numeric()
                        ->default(1)
                        ->required(),

                    Select::make('jenis_pihak')
                        ->options(JurnalPembantuItem::JENIS_PIHAK)
                        ->required()
                        ->searchable(),

                    TextInput::make('nama_pihak')
                        ->maxLength(255),

                    TextInput::make('nama_barang')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('no_dokumen')
                        ->maxLength(100),

                    TextInput::make('no_referensi')
                        ->maxLength(100),

                    Textarea::make('keterangan')
                        ->rows(2)
                        ->columnSpanFull(),

                    TextInput::make('ukuran')
                        ->maxLength(100),

                    TextInput::make('kualitas')
                        ->maxLength(100),

                    TextInput::make('banyak')
                        ->numeric()
                        ->step('0.000001')
                        ->live(),

                    TextInput::make('m3')
                        ->numeric()
                        ->step('0.000001')
                        ->live(),

                    TextInput::make('harga')
                        ->numeric()
                        ->step('0.000001')
                        ->required()
                        ->live(),

                    Select::make('hit_kbk')
                        ->label('Hitung Berdasarkan')
                        ->options(JurnalPembantuItem::HIT_KBK)
                        ->nullable()
                        ->live(),

                    TextInput::make('jumlah')
                        ->numeric()
                        ->step('0.0001')
                        ->disabled() // dihitung otomatis dari model
                        ->dehydrated(true),

                    Toggle::make('aktif')
                        ->default(true),
                    

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('urut')
            ->columns([
                TextColumn::make('urut')
                    ->sortable(),

                TextColumn::make('nama_barang')
                    ->searchable()
                    ->limit(25),

                TextColumn::make('nama_pihak')
                    ->searchable()
                    ->limit(20),

                TextColumn::make('banyak')
                    ->numeric(6)
                    ->alignRight(),

                TextColumn::make('m3')
                    ->numeric(6)
                    ->alignRight(),

                TextColumn::make('harga')
                    ->money('IDR')
                    ->alignRight(),

                TextColumn::make('jumlah')
                    ->money('IDR')
                    ->alignRight()
                    ->weight('bold'),

                IconColumn::make('status')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Item'),
            ])
            ->recordActions([
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