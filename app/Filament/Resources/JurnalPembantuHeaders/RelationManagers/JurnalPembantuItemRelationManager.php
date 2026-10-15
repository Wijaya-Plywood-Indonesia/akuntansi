<?php

namespace App\Filament\Resources\JurnalPembantuHeaders\RelationManagers;

use App\Models\Barang;
use App\Models\JurnalPembantuItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JurnalPembantuItemRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Detail Item';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            // ─────────────────────────────────────────────────────────
            //  IDENTITAS ITEM
            // ─────────────────────────────────────────────────────────
            Section::make('🏷️ Identitas Item')
                ->description('Nomor urut, pihak terkait, dan barang.')
                ->collapsible()
                ->schema([
                    Grid::make(3)->schema([

                        TextInput::make('urut')
                            ->label('No. Urut')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->helperText('Urutan baris dalam satu header.')
                            ->columnSpan(1),

                        Select::make('jenis_pihak')
                            ->label('Jenis Pihak')
                            ->options(JurnalPembantuItem::JENIS_PIHAK)
                            ->nullable()
                            ->native(false)
                            ->searchable()
                            ->columnSpan(1),

                        TextInput::make('nama_pihak')
                            ->label('Nama Pihak')
                            ->placeholder('Nama pelanggan / pemasok / karyawan')
                            ->maxLength(255)
                            ->columnSpan(1),
                    ]),

                    Grid::make(2)->schema([

                        // ── Selector Barang (dengan ID) ──────────────
                        Select::make('id_barang')
                            ->label('ID Barang')
                            ->options(
                                fn() => Barang::where('is_active', true)
                                    ->orderBy('kode_barang')
                                    ->get()
                                    ->mapWithKeys(fn($b) => [
                                        $b->id => "[{$b->kode_barang}]  {$b->nama_barang}",
                                    ])
                            )
                            ->searchable()
                            ->nullable()
                            ->native(false)
                            ->live()
                            ->helperText('Pilih barang untuk mengisi nama otomatis. (opsional)')
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $barang = Barang::find($state);
                                    if ($barang) {
                                        $set('nama_barang', $barang->nama_barang);
                                    }
                                }
                            })
                            ->columnSpan(1),

                        TextInput::make('nama_barang')
                            ->label('Nama Barang')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Nama barang / uraian item')
                            ->helperText('Terisi otomatis jika ID Barang dipilih, atau isi manual.')
                            ->columnSpan(1),
                    ]),
                ]),

            // ─────────────────────────────────────────────────────────
            //  DOKUMEN & KETERANGAN
            // ─────────────────────────────────────────────────────────
            Section::make('📄 Dokumen & Keterangan')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Grid::make(2)->schema([

                        TextInput::make('no_dokumen')
                            ->label('No. Dokumen')
                            ->placeholder('No. Surat Jalan / INV')
                            ->maxLength(100)
                            ->columnSpan(1),

                        TextInput::make('no_referensi')
                            ->label('No. Referensi')
                            ->placeholder('No. PO / referensi lain')
                            ->maxLength(100)
                            ->columnSpan(1),
                    ]),

                    Textarea::make('keterangan')
                        ->label('Keterangan Item')
                        ->rows(2)
                        ->placeholder('Uraian detail item ini…')
                        ->columnSpanFull(),
                ]),

            // ─────────────────────────────────────────────────────────
            //  SPESIFIKASI & HITUNG
            // ─────────────────────────────────────────────────────────
            Section::make('📐 Spesifikasi & Perhitungan')
                ->description('Ukuran, kualitas, volume, harga, dan cara menghitung jumlah.')
                ->collapsible()
                ->schema([
                    Grid::make(4)->schema([

                        TextInput::make('ukuran')
                            ->label('Ukuran')
                            ->placeholder('cth. 120×240')
                            ->maxLength(100)
                            ->columnSpan(1),

                        TextInput::make('kualitas')
                            ->label('Kualitas')
                            ->placeholder('cth. Grade A')
                            ->maxLength(100)
                            ->columnSpan(1),

                        TextInput::make('banyak')
                            ->label('Banyak (Qty)')
                            ->numeric()
                            ->step('0.000001')
                            ->placeholder('0')
                            ->live()
                            ->columnSpan(1),

                        TextInput::make('m3')
                            ->label('Volume (M³)')
                            ->numeric()
                            ->step('0.000001')
                            ->placeholder('0.000000')
                            ->live()
                            ->columnSpan(1),
                    ]),

                    Grid::make(3)->schema([

                        TextInput::make('harga')
                            ->label('Harga Satuan (Rp)')
                            ->numeric()
                            ->step('0.000001')
                            ->required()
                            ->placeholder('0')
                            ->live()
                            ->helperText('Harga per satuan (unit / m³ / lembar).')
                            ->columnSpan(1),

                        Select::make('hit_kbk')
                            ->label('Cara Hitung Jumlah')
                            ->options(JurnalPembantuItem::HIT_KBK)
                            ->nullable()
                            ->native(false)
                            ->live()
                            ->helperText('"× M³" atau "× Banyak" atau "Langsung" dari kolom Harga.')
                            ->columnSpan(1),

                        TextInput::make('jumlah')
                            ->label('Jumlah (Rp) — Otomatis')
                            ->numeric()
                            ->step('0.0001')
                            ->disabled()
                            ->dehydrated(true)
                            ->helperText('Dihitung otomatis dari server.')
                            ->columnSpan(1),
                    ]),

                    Toggle::make('status')
                        ->label('Item Aktif')
                        ->default(true)
                        ->helperText('Non-aktifkan untuk menyembunyikan dari perhitungan.'),
                ]),
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

                TextColumn::make('jenis_pihak')
                    ->searchable(),

                TextColumn::make('nama_pihak')
                    ->searchable()
                    ->limit(20),

                TextColumn::make('keterangan')
                    ->limit(100),
                TextColumn::make('hit_kbk')
                    ->limit(100),

                TextColumn::make('ukuran')
                    ->limit(30),

                TextColumn::make('banyak')
                    ->formatStateUsing(fn($state) => (float) $state)
                    ->alignRight(),

                TextColumn::make('m3')
                    ->numeric(6)
                    ->alignRight(),

                TextColumn::make('harga')
                    ->money('IDR')
                    ->alignRight(),
                //  TextColumn::make(JurnalPembantuItem::hitungJumlah1('harga', 'hit_kbk', 'm3', 'banyak'))
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
