<?php

namespace App\Filament\Resources\JurnalPembantuHeaders\Schemas;

use App\Models\Barang;
use App\Models\JurnalPembantuHeader;
use App\Models\SubAnakAkun;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JurnalPembantuHeaderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // ═══════════════════════════════════════════════════════════
            //  BAGIAN 1 — NOMOR & REFERENSI
            // ═══════════════════════════════════════════════════════════
            Section::make('🔢 Nomor & Referensi')
                ->description('Identifikasi unik jurnal dan dokumen sumbernya.')
                ->collapsible()
                ->schema([
                    Grid::make(3)->schema([

                        TextInput::make('no_jurnal_pembantu')
                            ->label('No. Jurnal Pembantu')
                            ->numeric()
                            ->required()
                            ->placeholder('cth. 10001')
                            ->helperText('ID unik baris ini di jurnal pembantu.')
                            ->columnSpan(1),

                        TextInput::make('jurnal')
                            ->label('No. Jurnal Umum')
                            ->numeric()
                            ->required()
                            ->placeholder('cth. 5001')
                            ->helperText('Satu nomor jurnal bisa berisi banyak pasangan D/K.')
                            ->columnSpan(1),

                        TextInput::make('no_dokumen')
                            ->label('No. Dokumen')
                            ->placeholder('No. DO / INV / PO / Surat Jalan')
                            ->maxLength(100)
                            ->nullable()
                            ->helperText('Nomor surat jalan, invoice, atau PO terkait.')
                            ->columnSpan(1),
                    ]),
                ]),

            // ═══════════════════════════════════════════════════════════
            //  BAGIAN 2 — JENIS & TANGGAL
            // ═══════════════════════════════════════════════════════════
            Section::make('📅 Jenis & Tanggal Transaksi')
                ->description('Klasifikasi transaksi dan tanggal terjadinya.')
                ->collapsible()
                ->schema([
                    Grid::make(3)->schema([

                        DatePicker::make('tgl_transaksi')
                            ->label('Tgl. Transaksi')
                            ->nullable()
                            ->helperText('Kosongkan jika sama dengan tanggal pencatatan.')
                            ->columnSpan(1),

                        Select::make('jenis_transaksi')
                            ->label('Jenis Transaksi')
                            ->options(JurnalPembantuHeader::JENIS)
                            ->required()
                            ->native(false)
                            ->helperText('Pilih sesuai jenis bukti transaksi.')
                            ->columnSpan(1),

                        Select::make('modul_asal')
                            ->label('Modul Asal')
                            ->options([
                                'penjualan'          => '🛒  Penjualan',
                                'pembelian'          => '📦  Pembelian',
                                'produksi_kupasan'   => '🪚  Produksi — Kupasan',
                                'produksi_dryer'     => '🔥  Produksi — Dryer',
                                'produksi_hotpress'  => '🗜️  Produksi — Hotpress',
                                'penggajian'         => '💼  Penggajian',
                                'lain'               => '📝  Lain-lain',
                            ])
                            ->nullable()
                            ->native(false)
                            ->helperText('Modul sistem yang menghasilkan jurnal ini.')
                            ->columnSpan(1),
                    ]),
                ]),

            // ═══════════════════════════════════════════════════════════
            //  BAGIAN 3 — AKUN & POSISI DEBET/KREDIT
            // ═══════════════════════════════════════════════════════════
            Section::make('📊 Akun & Posisi Debet / Kredit')
                ->description('Pilih akun buku besar dan tentukan posisi debet atau kredit.')
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([

                        Select::make('no_akun')
                            ->label('Akun Buku Besar')
                            ->options(
                                fn() => SubAnakAkun::where('status', 'aktif')
                                    ->orderBy('kode_sub_anak_akun')
                                    ->get()
                                    ->mapWithKeys(fn($a) => [
                                        $a->kode_sub_anak_akun => "[{$a->kode_sub_anak_akun}]  {$a->nama_sub_anak_akun}"
                                    ])
                            )
                            ->searchable()
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText('Ketik kode atau nama akun untuk mencari.')
                            ->afterStateUpdated(function ($state, callable $set) {
                                $akun = SubAnakAkun::where('kode_sub_anak_akun', $state)->first();
                                $set('nama_akun', $akun?->nama_sub_anak_akun);
                            })
                            ->columnSpan(1),

                        Select::make('map')
                            ->label('Posisi D / K')
                            ->options([
                                'd' => '⬆️  Debet  (D)',
                                'k' => '⬇️  Kredit (K)',
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Debet menambah aset/beban; Kredit menambah kewajiban/pendapatan.')
                            ->columnSpan(1),

                        // Cache nama akun — diisi otomatis oleh live() di atas
                        Hidden::make('nama_akun'),
                    ]),
                ]),

            // ═══════════════════════════════════════════════════════════
            //  BAGIAN 4 — KETERANGAN
            // ═══════════════════════════════════════════════════════════
            Section::make('📝 Keterangan')
                ->description('Uraian transaksi dan catatan tambahan untuk keperluan internal.')
                ->collapsible()
                ->schema([
                    Textarea::make('keterangan')
                        ->label('Keterangan Transaksi')
                        ->required()
                        ->rows(3)
                        ->placeholder('Contoh: Penjualan kayu tripleks ke PT. Maju Jaya — Inv. 2026/001')
                        ->helperText('Uraian ini akan diteruskan ke Jurnal Umum.')
                        ->columnSpanFull(),

                    Textarea::make('catatan_internal')
                        ->label('Catatan Internal')
                        ->rows(2)
                        ->nullable()
                        ->placeholder('Catatan khusus staf akuntansi — tidak tampil di laporan.')
                        ->helperText('⚠️ Tidak diteruskan ke Jurnal Umum.')
                        ->columnSpanFull(),
                ]),

            Hidden::make('dibuat_oleh')
                ->default(fn() => Filament::auth()->id())
                ->dehydrated(),
        ]);
    }
}
