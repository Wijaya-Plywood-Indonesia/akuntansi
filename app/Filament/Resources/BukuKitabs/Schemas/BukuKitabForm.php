<?php

namespace App\Filament\Resources\BukuKitabs\Schemas;

use App\Models\SubAnakAkun;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BukuKitabForm
{
    /** Cari nama akun dari kode (khusus SubAnakAkun) */
    protected static function cariNamaAkun(?string $kode): ?string
    {
        if (!$kode) {
            return null;
        }

        return SubAnakAkun::where('kode_sub_anak_akun', $kode)->value('nama_sub_anak_akun');
    }

    /** Opsi akun untuk dropdown — HANYA SubAnakAkun, format "kode - nama" */
    protected static function opsiAkun(): array
    {
        return SubAnakAkun::pluck('nama_sub_anak_akun', 'kode_sub_anak_akun')
            ->mapWithKeys(fn($nama, $kode) => [$kode => "{$kode} — {$nama}"])
            ->toArray();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Informasi Dasar')
                ->description('Identitas jenis transaksi yang akan dipanggil modul lain (Penjualan, Pembelian, dsb).')
                ->icon('heroicon-o-identification')
                ->columns(2)
                ->schema([
                    TextInput::make('kode')
                        ->label('Kode')
                        ->helperText('Huruf besar, tanpa spasi. Dipakai modul lain untuk memanggil template. Contoh: PENJUALAN_LOGCORE')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->prefixIcon('heroicon-o-hashtag'),

                    TextInput::make('nama')
                        ->label('Nama')
                        ->required()
                        ->maxLength(150)
                        ->prefixIcon('heroicon-o-tag'),

                    TextInput::make('kategori')
                        ->label('Kategori')
                        ->helperText('Opsional, untuk pengelompokan. Contoh: Penjualan, Pembelian, Produksi')
                        ->maxLength(100)
                        ->datalist(fn() => \App\Models\BukuKitab::whereNotNull('kategori')->distinct()->pluck('kategori')->toArray())
                        ->prefixIcon('heroicon-o-folder'),

                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->helperText('Nonaktifkan jika template ini sedang tidak dipakai')
                        ->default(true)
                        ->inline(false),

                    Textarea::make('keterangan')
                        ->label('Catatan')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('Baris Pemetaan Akun')
                ->description('Urutan baris akan mengikuti urutan di sini. Nama akun & posisi D/K terisi otomatis saat memilih akun.')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Repeater::make('akunDetail')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            Select::make('no_akun')
                                ->label('Akun (Sub Anak Akun)')
                                ->options(fn() => self::opsiAkun())
                                ->searchable()
                                ->native(false)
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn($state, callable $set) =>
                                    $set('nama_akun', self::cariNamaAkun($state))
                                )
                                ->columnSpan(3),

                            ToggleButtons::make('posisi')
                                ->label('D/K')
                                ->options([
                                    'd' => 'Debit',
                                    'k' => 'Kredit',
                                ])
                                ->colors([
                                    'd' => 'success',
                                    'k' => 'danger',
                                ])
                                ->icons([
                                    'd' => 'heroicon-o-arrow-down-circle',
                                    'k' => 'heroicon-o-arrow-up-circle',
                                ])
                                ->inline()
                                ->required()
                                ->columnSpan(2),

                            Select::make('variabel_nilai')
                                ->label('Variabel Nilai')
                                ->helperText('Nilai baris ini diambil dari mana. Daftar ini dikelola programmer — hubungi tim IT kalau butuh jenis nilai baru.')
                                ->options(fn() => \App\Support\KatalogVariabelKitab::options())
                                ->searchable()
                                ->native(false)
                                ->required()
                                ->prefixIcon('heroicon-o-variable')
                                ->columnSpan(3),

                            Hidden::make('nama_akun')
                                ->dehydrated(),

                            Placeholder::make('preview_nama_akun')
                                ->label('Nama Akun Terpilih')
                                ->content(fn(callable $get) => self::cariNamaAkun($get('no_akun')) ?? '—')
                                ->columnSpan(3),

                            TextInput::make('keterangan')
                                ->label('Keterangan')
                                ->helperText('Opsional, mis. "Mencatat kehilangan"')
                                ->maxLength(150)
                                ->columnSpan(2),
                        ])
                        ->columns(5)
                        ->orderColumn('urut')
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->collapsed()
                        ->itemLabel(fn(array $state): ?string =>
                            trim(($state['no_akun'] ?? '-') . ' — ' . (self::cariNamaAkun($state['no_akun'] ?? null) ?? '') . ' (' . strtoupper($state['posisi'] ?? '-') . ')' . (($state['variabel_nilai'] ?? null) ? ' · ' . (\App\Support\KatalogVariabelKitab::OPSI[$state['variabel_nilai']] ?? $state['variabel_nilai']) : ''))
                        )
                        ->defaultItems(1)
                        ->addActionLabel('+ Tambah Baris Akun'),
                ]),
        ]);
    }
}