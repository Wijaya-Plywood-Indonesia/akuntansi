<?php

namespace App\Filament\Resources\KelompokMappingAkuns\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class KelompokMappingAkunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_kelompok')
                    ->label('Kode Kelompok')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(30)
                    ->placeholder('Contoh: WIP_VENEER_BASAH'),

                TextInput::make('nama_kelompok')
                    ->label('Nama Kelompok')
                    ->required()
                    ->maxLength(100),

                Select::make('kode_proses')
                    ->label('Proses Produksi')
                    ->placeholder('— Lintas Proses (kosongkan jika berlaku semua) —')
                    ->options(self::opsiProses())
                    ->nullable(),

                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }
    public static function opsiProses(): array
    {
        return [
            'LOG_RECEIVING' => 'Penerimaan Log / Kayu',
            'ROTARY' => 'Rotary / Peeling',
            'DRYER' => 'Dryer / Pengeringan',
            'REPAIR' => 'Repair / Jointing Veneer',
            'GLUING' => 'Gluing / Pelaburan Lem',
            'HOT_PRESS' => 'Hot Press / Kempa Panas',
            'COLD_PRESS' => 'Cold Press / Kempa Dingin',
            'FINISHING' => 'Finishing / Sanding',
            'PACKING' => 'Packing / Pengepakan',
        ];
    }
}
