<?php

namespace App\Filament\Resources\SubAnakAkuns\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SubAnakAkunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id_anak_akun')
                    ->required()
                    ->numeric(),
                TextInput::make('kode_sub_anak_akun')
                    ->required(),
                TextInput::make('nama_sub_anak_akun')
                    ->default('Tidak Punya Nama Akun'),
                Textarea::make('keterangan')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('aktif'),
                TextInput::make('saldo normal'),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
