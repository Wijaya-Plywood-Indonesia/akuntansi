<?php

namespace App\Filament\Resources\Pembelians\RelationManagers;

use App\Models\Barang;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DetailPembeliansRelationManager extends RelationManager
{
    protected static string $relationship = 'detailPembelians';

    protected static ?string $title = 'Detail Barang';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('barang_id')
                    ->label('Barang')
                    ->options(
                        Barang::query()
                            ->select(['id', 'kode_barang', 'nama_barang'])
                            ->get()
                            ->mapWithKeys(fn(Barang $b) => [
                                $b->id => "[{$b->kode_barang}] {$b->nama_barang}",
                            ])
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?int $state, Set $set) {
                        if (!$state) return;

                        $barang = Barang::with('satuan')->find($state);
                        if (!$barang) return;

                        $set('kode_barang', $barang->kode_barang);
                        $set('nama_barang', $barang->nama_barang);
                        $set('harga_beli',  $barang->harga_beli ?? 0);
                        $set(
                            'satuan',
                            is_object($barang->satuan)
                                ? ($barang->satuan->nama ?? $barang->satuan->keterangan ?? 'Unit')
                                : ($barang->satuan ?? 'Unit')
                        );

                        $set('subtotal', $barang->harga_beli ?? 0);
                    })
                    ->columnSpanFull(),

                TextInput::make('kode_barang')
                    ->label('Kode Barang')
                    ->disabled()
                    ->dehydrated()
                    ->placeholder('Otomatis terisi...'),

                TextInput::make('nama_barang')
                    ->label('Nama Barang')
                    ->disabled()
                    ->dehydrated()
                    ->placeholder('Otomatis terisi...'),

                TextInput::make('qty')
                    ->label('Jumlah (Qty)')
                    ->numeric()
                    ->minValue(0.01)
                    ->default(1)
                    ->required()
                    ->live(debounce: 500)
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        self::hitungSubtotal($get, $set);
                    }),
                    
                TextInput::make('kubikasi')
                    ->label('Kubikasi (M³)')
                    ->numeric()
                    ->default(0)
                    ->step(0.0001)
                    ->live(debounce: 500)
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        self::hitungSubtotal($get, $set);
                    }),
                    
                Select::make('hitung_dari')
                    ->label('Dasar Harga')
                    ->options([
                        'qty' => 'Quantity (Lembar/Pcs)',
                        'm3'  => 'Kubikasi (M³)'
                    ])
                    ->default('qty')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        self::hitungSubtotal($get, $set);
                    }),

                TextInput::make('satuan')
                    ->label('Satuan')
                    ->placeholder('Pcs / Kg...')
                    ->maxLength(50),

                TextInput::make('harga_beli')
                    ->label('Harga Beli (Rp)')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->prefix('Rp')
                    ->live(debounce: 500)
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        self::hitungSubtotal($get, $set);
                    }),

                TextInput::make('diskon')
                    ->label('Diskon Item (Rp)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('Rp')
                    ->live(debounce: 500)
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        self::hitungSubtotal($get, $set);
                    }),

                TextInput::make('subtotal')
                    ->label('Subtotal (Rp)')
                    ->numeric()
                    ->disabled()
                    ->dehydrated()
                    ->prefix('Rp')
                    ->default(0),

                Textarea::make('catatan')
                    ->label('Catatan')
                    ->placeholder('Catatan opsional untuk barang ini...')
                    ->rows(2)
                    ->columnSpanFull(),

            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_barang')
            ->columns([

                TextColumn::make('kode_barang')
                    ->label('Kode')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('qty')
                    ->label('Qty')
                    ->sortable()
                    ->alignCenter(),
                    
                TextColumn::make('kubikasi')
                    ->label('M³')
                    ->sortable()
                    ->alignCenter(),
                    
                TextColumn::make('hitung_dari')
                    ->label('Dasar Harga')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'qty' => 'info',
                        'm3' => 'warning',
                    })
                    ->alignCenter(),

                TextColumn::make('satuan')
                    ->label('Satuan')
                    ->alignCenter(),

                TextColumn::make('harga_beli')
                    ->label('Harga Beli')
                    ->money('IDR')
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('diskon')
                    ->label('Diskon')
                    ->money('IDR')
                    ->sortable()
                    ->alignRight()
                    ->color('danger'),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('IDR')
                    ->sortable()
                    ->alignRight()
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('catatan')
                    ->label('Catatan')
                    ->limit(40)
                    ->tooltip(fn($record) => $record->catatan)
                    ->placeholder('-'),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Barang')
                    ->modalHeading('Tambah Detail Barang')
                    ->mutateFormDataUsing(function (array $data): array {
                        $qty         = (float) ($data['qty']        ?? 0);
                        $kubikasi    = (float) ($data['kubikasi']   ?? 0);
                        $harga       = (float) ($data['harga_beli'] ?? 0);
                        $diskon      = (float) ($data['diskon']     ?? 0);
                        $hitung_dari = $data['hitung_dari'] ?? 'qty';

                        $pengali = ($hitung_dari === 'm3') ? $kubikasi : $qty;
                        $data['subtotal'] = max(0, ($pengali * $harga) - $diskon);

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([10, 25, 50]);
    }

    protected static function hitungSubtotal(Get $get, Set $set): void
    {
        $qty         = (float) ($get('qty') ?? 0);
        $kubikasi    = (float) ($get('kubikasi') ?? 0);
        $harga       = (float) ($get('harga_beli')  ?? 0);
        $diskon      = (float) ($get('diskon')       ?? 0);
        $hitung_dari = $get('hitung_dari') ?? 'qty';
        
        $pengali = ($hitung_dari === 'm3') ? $kubikasi : $qty;
        
        $set('subtotal', max(0, ($pengali * $harga) - $diskon));
    }
}