<?php

namespace App\Filament\Resources\JurnalPembantuHeaders\Schemas;

use App\Models\JurnalPembantuHeader;
use App\Models\SubAnakAkun;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class JurnalPembantuHeaderForm
{
    /**
     * Pilihan Modul Asal. Nilai 'produksi', 'pembelian_kayu' dan 'rotary'
     * dihasilkan otomatis oleh API dari ERP, jadi harus ada di daftar ini,
     * kalau tidak form edit menolak simpan ("The selected modul asal is invalid").
     */
    public static function opsiModulAsal(?string $nilaiSaatIni = null): array
    {
        $opsi = [
            'penjualan' => 'Penjualan',
            'pembelian' => 'Pembelian',
            'pembelian_kayu' => 'Pembelian Kayu',
            'produksi' => 'Produksi (ERP)',
            'rotary' => 'Produksi Rotary',
            'produksi_kupasan' => 'Produksi Kupasan',
            'produksi_dryer' => 'Produksi Dryer',
            'produksi_hotpress' => 'Produksi Hotpress',
            'penggajian' => 'Penggajian',
            'lain' => 'Lain-lain',
        ];

        // Nilai lama / tak dikenal pada baris yang diedit tetap ditampilkan
        // dan dianggap valid, supaya edit keterangan saja tidak pernah gagal.
        if (filled($nilaiSaatIni) && ! array_key_exists($nilaiSaatIni, $opsi)) {
            $opsi[$nilaiSaatIni] = $nilaiSaatIni;
        }

        return $opsi;
    }

    public static function configure(Schema $schema): Schema
    {
        $kolom = fn (int $md) => ['default' => 1, 'md' => $md];

        return $schema->components([

            // ═══════════════════════════════════════════════════════════
            //  BARIS 1 — IDENTITAS: No. Dokumen, Tanggal, Nomor Jurnal
            // ═══════════════════════════════════════════════════════════
            Grid::make(['default' => 1, 'md' => 12])
                ->columnSpanFull()
                ->schema([
                    TextInput::make('no_dokumen')
                        ->label('No. Dokumen')
                        ->maxLength(100)
                        ->nullable()
                        ->columnSpan($kolom(4)),

                    DatePicker::make('tgl_transaksi')
                        ->label('Tgl. Transaksi')
                        ->nullable()
                        ->columnSpan($kolom(2)),

                    TextInput::make('no_jurnal_pembantu')
                        ->label('No. Jurnal Pembantu')
                        ->numeric()
                        ->required()
                        ->columnSpan($kolom(3)),

                    TextInput::make('jurnal')
                        ->label('No. Jurnal Umum')
                        ->numeric()
                        ->required()
                        ->columnSpan($kolom(3)),
                ]),

            // ═══════════════════════════════════════════════════════════
            //  BARIS 2 — AKUN, D/K, JENIS, MODUL
            // ═══════════════════════════════════════════════════════════
            Grid::make(['default' => 1, 'md' => 12])
                ->columnSpanFull()
                ->schema([
                    Select::make('no_akun')
                        ->label('Akun Buku Besar')
                        ->options(
                            fn () => SubAnakAkun::where('status', 'aktif')
                                ->orderBy('kode_sub_anak_akun')
                                ->get()
                                ->mapWithKeys(fn ($a) => [
                                    $a->kode_sub_anak_akun => "[{$a->kode_sub_anak_akun}]  {$a->nama_sub_anak_akun}",
                                ])
                        )
                        ->searchable()
                        ->required()
                        ->live()
                        ->native(false)
                        ->afterStateUpdated(function ($state, callable $set) {
                            $akun = SubAnakAkun::where('kode_sub_anak_akun', $state)->first();
                            $set('nama_akun', $akun?->nama_sub_anak_akun);
                        })
                        ->columnSpan($kolom(4)),

                    Select::make('map')
                        ->label('Posisi D / K')
                        ->options([
                            'd' => 'Debet (D)',
                            'k' => 'Kredit (K)',
                        ])
                        ->required()
                        ->native(false)
                        ->columnSpan($kolom(2)),

                    Select::make('jenis_transaksi')
                        ->label('Jenis Transaksi')
                        ->options(JurnalPembantuHeader::JENIS)
                        ->required()
                        ->native(false)
                        ->columnSpan($kolom(3)),

                    Select::make('modul_asal')
                        ->label('Modul Asal')
                        ->options(fn (?JurnalPembantuHeader $record) => self::opsiModulAsal($record?->modul_asal))
                        ->nullable()
                        ->native(false)
                        ->columnSpan($kolom(3)),

                    // Cache nama akun — diisi otomatis oleh live() di atas
                    Hidden::make('nama_akun'),
                ]),

            // ═══════════════════════════════════════════════════════════
            //  BARIS 3 — KETERANGAN & CATATAN INTERNAL
            // ═══════════════════════════════════════════════════════════
            Grid::make(['default' => 1, 'md' => 12])
                ->columnSpanFull()
                ->schema([
                    Textarea::make('keterangan')
                        ->label('Keterangan Transaksi')
                        ->required()
                        ->rows(3)
                        ->columnSpan($kolom(8)),

                    Textarea::make('catatan_internal')
                        ->label('Catatan Internal')
                        ->rows(3)
                        ->nullable()
                        ->columnSpan($kolom(4)),
                ]),

            Hidden::make('dibuat_oleh')
                ->default(fn () => Filament::auth()->id())
                ->dehydrated(),
        ]);
    }
}