<?php

namespace App\Filament\Resources\KelompokMappingAkuns\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KelompokMappingAkunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Kelompok')
                    ->schema([
                        TextEntry::make('kode_kelompok')
                            ->label('Kode Kelompok'),

                        TextEntry::make('nama_kelompok')
                            ->label('Nama Kelompok'),

                        TextEntry::make('kode_proses')
                            ->label('Proses Produksi')
                            ->badge()
                            ->default('— Lintas Proses —'),

                        TextEntry::make('keterangan')
                            ->label('Keterangan')
                            ->default('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Riwayat')
                    ->schema([
                        TextEntry::make('dibuatOleh.name')
                            ->label('Dibuat Oleh')
                            ->default('-'),

                        TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime('d M Y, H:i'),

                        TextEntry::make('dieditOleh.name')
                            ->label('Diedit Oleh')
                            ->default('-'),

                        TextEntry::make('updated_at')
                            ->label('Diedit Pada')
                            ->dateTime('d M Y, H:i'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
}
