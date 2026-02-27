<?php

namespace App\Filament\Resources\AnakAkuns\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AnakAkunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id_induk_akun')
                    ->required()
                    ->numeric(),
                TextInput::make('kode_anak_akun')
                    ->required(),
                TextInput::make('nama_anak_akun')
                    ->default('Tidak Punya Nama Akun'),
                Textarea::make('keterangan')
                    ->columnSpanFull(),
                TextInput::make('parent')
                    ->numeric(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('saldo normal'),
                TextInput::make('status')
                    ->required()
                    ->default('aktif'),
            ]);
    }
}
