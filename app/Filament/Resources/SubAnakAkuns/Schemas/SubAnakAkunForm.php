<?php

namespace App\Filament\Resources\SubAnakAkuns\Schemas;

use App\Models\SubAnakAkun;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SubAnakAkunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_anak_akun')
                    ->relationship('anakAkun', 'nama_anak_akun')
                    ->searchable()
                    ->required(),

                TextInput::make('kode_sub_anak_akun')
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('nama_sub_anak_akun')
                    ->required(),

                Select::make('saldo_normal')
                    ->options([
                        'debet' => 'Debet',
                        'kredit' => 'Kredit',
                    ])
                    ->required(),

                Select::make('status')
                    ->options([
                        'aktif' => 'Aktif',
                        'nonaktif' => 'Nonaktif',
                    ])
                    ->default('aktif')
                    ->required(),

                Select::make('tipe_buku_pembantu')
                    ->label('Buku Pembantu')
                    ->helperText('Menentukan modal apa yang muncul saat akun ini diklik di Chart of Accounts. Kosongkan kalau akun ini bukan Piutang (mis. akun Persediaan tetap pakai Barang Terkait secara otomatis).')
                    ->options(SubAnakAkun::TIPE_BUKU_PEMBANTU)
                    ->native(false)
                    ->placeholder('Default (Barang Terkait, kalau ada)'),

                Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }
}