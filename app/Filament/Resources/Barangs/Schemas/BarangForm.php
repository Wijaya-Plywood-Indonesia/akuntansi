<?php

namespace App\Filament\Resources\Barangs\Schemas;

use App\Models\Kategori;
use App\Models\SubAnakAkun;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_barang')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),

                TextInput::make('barcode')
                    ->unique(ignoreRecord: true)
                    ->maxLength(100),

                TextInput::make('nama_barang')
                    ->required()
                    ->maxLength(255),

                Select::make('id_kategori')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama_kategori')
                    ->preload()
                    ->searchable()
                    ->required()

                    // ── 1. FORM DI DALAM MODAL POP-UP ────────────────────────────────────────
                    ->createOptionForm([
                        TextInput::make('nama_kategori')
                            ->label('Nama Kategori')
                            ->required()
                            ->maxLength(255),

                        Select::make('parent_id')
                            ->label('Parent Kategori')
                            // Mengambil relasi parent dari model Kategori Anda
                            ->relationship('parent', 'nama_kategori')
                            ->searchable()
                            ->preload() // Memuat data agar langsung muncul saat diklik
                            ->placeholder('Kategori Utama (Kosongkan jika tidak ada)')
                            ->nullable(),
                    ])
                    ->createOptionUsing(function (array $data) {
                        $kategori = Kategori::create([
                            'nama_kategori' => $data['nama_kategori'],
                            'parent_id'     => $data['parent_id'] ?? null,
                        ]);

                        return $kategori->id;
                    }),

                Select::make('id_satuan')
                    ->label('Satuan')
                    ->relationship('satuan', 'nama_satuan')
                    ->preload()
                    ->searchable()
                    ->required(),

                TextInput::make('harga_beli')
                    ->label('HPP')
                    ->numeric()
                    ->prefix('Rp'),

                TextInput::make('harga_jual')
                    ->numeric()
                    ->prefix('Rp'),

                TextInput::make('stok_minimum')
                    ->required()
                    ->numeric()
                    ->default(0),

                // ── AKUN PERSEDIAAN ──────────────────────────────────────────
                Select::make('id_sub_anak_akun')
                    ->label('Akun Persediaan (Inventory)')
                    ->helperText('Akun jurnal untuk mencatat masuk/keluarnya stok barang ini.')
                    ->searchable()
                    ->nullable()
                    ->options(
                        fn() => SubAnakAkun::orderBy('kode_sub_anak_akun')
                            ->get()
                            ->mapWithKeys(fn($a) => [
                                $a->id => "{$a->kode_sub_anak_akun} — {$a->nama_sub_anak_akun}"
                            ])
                    )
                    ->getSearchResultsUsing(
                        fn(string $search) => SubAnakAkun::where('kode_sub_anak_akun', 'like', "%{$search}%")
                            ->orWhere('nama_sub_anak_akun', 'like', "%{$search}%")
                            ->limit(30)
                            ->get()
                            ->mapWithKeys(fn($a) => [
                                $a->id => "{$a->kode_sub_anak_akun} — {$a->nama_sub_anak_akun}"
                            ])
                    )
                    ->getOptionLabelUsing(
                        fn($value) => SubAnakAkun::find($value) !== null
                            ? SubAnakAkun::find($value)->kode_sub_anak_akun . ' — ' . SubAnakAkun::find($value)->nama_sub_anak_akun
                            : null
                    ),

                // ── AKUN PENDAPATAN BARU ─────────────────────────────────────
                Select::make('akun_pendapatan_id')
                    ->label('Akun Pendapatan (Sales/Revenue)')
                    ->helperText('Akun jurnal untuk mencatat pendapatan saat barang ini terjual.')
                    ->searchable()
                    ->nullable()
                    ->options(
                        fn() => SubAnakAkun::orderBy('kode_sub_anak_akun')
                            ->get()
                            ->mapWithKeys(fn($a) => [
                                $a->id => "{$a->kode_sub_anak_akun} — {$a->nama_sub_anak_akun}"
                            ])
                    )
                    ->getSearchResultsUsing(
                        fn(string $search) => SubAnakAkun::where('kode_sub_anak_akun', 'like', "%{$search}%")
                            ->orWhere('nama_sub_anak_akun', 'like', "%{$search}%")
                            ->limit(30)
                            ->get()
                            ->mapWithKeys(fn($a) => [
                                $a->id => "{$a->kode_sub_anak_akun} — {$a->nama_sub_anak_akun}"
                            ])
                    )
                    ->getOptionLabelUsing(
                        fn($value) => SubAnakAkun::find($value) !== null
                            ? SubAnakAkun::find($value)->kode_sub_anak_akun . ' — ' . SubAnakAkun::find($value)->nama_sub_anak_akun
                            : null
                    ),

                // ── AKUN HPP BARU ────────────────────────────────────────────
                Select::make('akun_hpp_id')
                    ->label('Akun HPP (COGS)')
                    ->helperText('Akun jurnal untuk mencatat Beban Pokok Penjualan saat barang ini terjual.')
                    ->searchable()
                    ->nullable()
                    ->options(
                        fn() => SubAnakAkun::orderBy('kode_sub_anak_akun')
                            ->get()
                            ->mapWithKeys(fn($a) => [
                                $a->id => "{$a->kode_sub_anak_akun} — {$a->nama_sub_anak_akun}"
                            ])
                    )
                    ->getSearchResultsUsing(
                        fn(string $search) => SubAnakAkun::where('kode_sub_anak_akun', 'like', "%{$search}%")
                            ->orWhere('nama_sub_anak_akun', 'like', "%{$search}%")
                            ->limit(30)
                            ->get()
                            ->mapWithKeys(fn($a) => [
                                $a->id => "{$a->kode_sub_anak_akun} — {$a->nama_sub_anak_akun}"
                            ])
                    )
                    ->getOptionLabelUsing(
                        fn($value) => SubAnakAkun::find($value) !== null
                            ? SubAnakAkun::find($value)->kode_sub_anak_akun . ' — ' . SubAnakAkun::find($value)->nama_sub_anak_akun
                            : null
                    ),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
