<?php

namespace App\Filament\Resources\ReturnPenjualans\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReturnPenjualanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Retur')
                    ->description('Data transaksi retur dan nota penjualan asal')
                    ->icon('heroicon-o-receipt-refund')
                    ->columns(3)
                    ->schema([
                        TextInput::make('no_retur')
                            ->label('Nomor Retur')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('no_nota')
                            ->label('Nomor Nota Penjualan')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('tanggal')
                            ->label('Tanggal Retur')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('nama_customer')
                            ->label('Nama Customer')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('status_return')
                            ->label('Status')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('jenis_retur')
                            ->label('Jenis Retur')
                            ->disabled()
                            ->dehydrated(),
                    ]),

                Section::make('Buku Kitab & Pengembalian Dana')
                    ->description('Informasi akuntansi dan template Buku Kitab yang diterapkan')
                    ->icon('heroicon-o-book-open')
                    ->columns(2)
                    ->schema([
                        TextInput::make('kode_kitab')
                            ->label('Kode Buku Kitab')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('bank')
                            ->label('Akun / Sumber Pengembalian')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('sub_total')
                            ->label('Subtotal Nilai Retur')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('ppn_nominal')
                            ->label('PPN Nominal')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('total')
                            ->label('Total Nilai Retur')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(),

                        Textarea::make('keterangan')
                            ->label('Catatan Retur')
                            ->disabled()
                            ->dehydrated()
                            ->rows(2),
                    ]),

                Section::make('Jurnal Pembantu (Akuntansi)')
                    ->description('Status pencatatan transaksi di Jurnal Pembantu Header')
                    ->icon('heroicon-o-document-chart-bar')
                    ->columns(3)
                    ->schema([
                        TextInput::make('no_jurnal_display')
                            ->label('Nomor Jurnal Pembantu')
                            ->formatStateUsing(fn($record) => $record?->no_jurnal ? "Jurnal #{$record->no_jurnal}" : 'Belum Ada Jurnal')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('status_jurnal_display')
                            ->label('Status Jurnal')
                            ->formatStateUsing(function ($record) {
                                $header = $record?->jurnalPembantuHeaders()->first();
                                return $header ? strtoupper($header->status) : 'BELUM DITERBITKAN';
                            })
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('baris_jurnal_display')
                            ->label('Total Baris Header Akun')
                            ->formatStateUsing(fn($record) => $record?->jurnalPembantuHeaders()->count() ? "{$record->jurnalPembantuHeaders()->count()} Baris Akun" : '-')
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Rincian Barang yang Diretur')
                    ->description('Daftar item barang dan jumlah pengembalian')
                    ->icon('heroicon-o-cube')
                    ->schema([
                        Repeater::make('details_return')
                            ->relationship('details_return')
                            ->schema([
                                TextInput::make('nama_barang')
                                    ->label('Nama Barang')
                                    ->disabled(),

                                TextInput::make('satuan')
                                    ->label('Satuan')
                                    ->disabled(),

                                TextInput::make('harga_jual')
                                    ->label('Harga Jual')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled(),

                                TextInput::make('qty')
                                    ->label('Qty Diretur')
                                    ->numeric()
                                    ->disabled(),

                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled(),

                                TextInput::make('keterangan')
                                    ->label('Alasan Retur')
                                    ->disabled(),
                            ])
                            ->columns(3)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ]),
            ]);
    }
}
