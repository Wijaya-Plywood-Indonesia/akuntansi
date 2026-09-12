<?php

namespace App\Filament\Resources\Pembelians\Schemas;

use App\Models\Pembelian;
use App\Models\PembelianMetodePembayaran;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class PembeliansInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 
                Section::make('Informasi Pembelian')
                    ->schema([
                        TextEntry::make('nomor_nota')
                            ->label('Nomor Nota'),

                        TextEntry::make('tanggal')
                            ->label('Tanggal')
                            ->date('d M Y'),

                        TextEntry::make('supplier_name')
                            ->label('Supplier'),

                        TextEntry::make('supplier_phone')
                            ->label('Telepon'),

                        TextEntry::make('supplier.npwp')
                            ->label('NPWP'),

                        TextEntry::make('supplier_address')
                            ->label('Alamat')
                            ->columnSpanFull(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(function ($record, $state) {
                                // Jika belum ada validator, paksa ambil label 'Belum Diproses'
                                if (empty($record->validated_by)) {
                                    return Pembelian::labelStatus()[Pembelian::STATUS_DRAFT] ?? 'Belum Diproses';
                                }

                                // Jika sudah divalidasi, tampilkan status sesuai data di database
                                return Pembelian::labelStatus()[$state] ?? $state;
                            })
                            ->color(function ($record, $state) {
                                // Berikan warna abu-abu (gray) untuk status yang belum diproses
                                if (empty($record->validated_by)) {
                                    return 'gray';
                                }

                                // Gunakan warna dari helper model jika sudah divalidasi
                                return Pembelian::warnaBadgeStatus()[$state] ?? 'gray';
                            }),

                        TextEntry::make('catatan')
                            ->label('Catatan')
                            ->columnSpanFull(),
                        TextEntry::make('createdBy.name')
                            ->label('Dibuat Oleh'),

                        TextEntry::make('validatedBy.name')
                            ->label('Divalidasi Oleh')
                            ->placeholder('-'),
                    ])
                    ->columns(2),

                Section::make('Riwayat / Metode Pembayaran')
                    ->description('Detail pembayaran untuk transaksi ini.')
                    ->schema([
                        // Asumsi: Relasi hasMany di Model Pembelian bernama 'pembayarans' atau 'metodePembayarans'
                        // Silakan sesuaikan nama string di bawah ini dengan nama relasi di model Pembelian Anda
                        RepeatableEntry::make('metodePembayarans')
                            ->hiddenLabel() // Sembunyikan label karena sudah ada judul Section
                            ->schema([
                                TextEntry::make('tanggal_bayar')
                                    ->label('Tanggal Bayar')
                                    ->date('d M Y'),

                                TextEntry::make('payment_method')
                                    ->label('Metode')
                                    ->badge()
                                    ->formatStateUsing(fn(string $state) => PembelianMetodePembayaran::labelMetode()[$state] ?? $state)
                                    ->color(fn(string $state) => match ($state) {
                                        PembelianMetodePembayaran::METODE_TUNAI => 'success',
                                        PembelianMetodePembayaran::METODE_TRANSFER => 'info',
                                        PembelianMetodePembayaran::METODE_CICILAN => 'warning',
                                        PembelianMetodePembayaran::METODE_LAINNYA => 'primary',
                                        default => 'gray',
                                    }),

                                TextEntry::make('reference_number')
                                    ->label('No. Referensi / Rekening')
                                    ->placeholder('-') // Jika tunai biasanya kosong, tampilkan '-'
                                    ->copyable(),

                                TextEntry::make('amount')
                                    ->label('Nominal Terbayar')
                                    ->badge()
                                    ->color(function ($record) {
                                        // Ambil grand_total dari relasi pembelian
                                        $grandTotal = $record->pembelian->grand_total ?? 0;
                                        $nominalBayar = $record->amount ?? 0;

                                        // Jika bayarnya kurang dari total tagihan, berikan warna orange (warning)
                                        // Jika pas atau lebih (lunas), berikan warna hijau (success)
                                        return $nominalBayar < $grandTotal ? 'warning' : 'success';
                                    })
                                    ->formatStateUsing(function ($record, $state) {
                                        $grandTotal = $record->pembelian->grand_total ?? 0;
                                        $nominalBayar = $record->amount ?? 0;

                                        $formattedAmount = 'Rp ' . number_format($state, 0, ',', '.');

                                        // Opsional: Tambahkan teks tambahan jika belum lunas agar lebih informatif
                                        return $nominalBayar < $grandTotal
                                            ? "{$formattedAmount}"
                                            : "{$formattedAmount} (Lunas)";
                                    }),

                                TextEntry::make('createdBy.name')
                                    ->label('Diinput Oleh'),

                                TextEntry::make('validatedBy.name')
                                    ->label('Divalidasi Oleh')
                                    ->placeholder('Belum Divalidasi'),

                                TextEntry::make('catatan')
                                    ->label('Catatan')
                                    ->columnSpanFull()
                                    ->placeholder('Tidak ada catatan.'),
                            ])
                            ->columns(3) // Tampilan akan dipecah jadi 3 kolom agar hemat ruang
                    ]),

                Section::make('Nominal')
                    ->schema([
                        TextEntry::make('sub_total')
                            ->label('Sub Total'),

                        TextEntry::make('total_diskon')
                            ->label('Diskon'),

                        TextEntry::make('total_ppn')
                            ->label('PPN'),

                        TextEntry::make('ongkir')
                            ->label('Ongkir'),

                        TextEntry::make('biaya_lain')
                            ->label('Biaya Lain'),

                        TextEntry::make('grand_total')
                            ->label('Grand Total')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(function ($state) {
                                return 'Rp ' . number_format($state, 0, ',', '.');
                            }),
                    ])
                    ->columns(3),



                Section::make('Foto Nota')
                    ->schema([
                        ImageEntry::make('foto')
                            ->hiddenLabel()
                            ->disk('public')         // ← karena foto adalah array (multiple upload)
                            ->visibility('public')
                            ->imageHeight(120)
                            ->extraImgAttributes(['class' => 'cursor-pointer hover:opacity-80 transition rounded-lg'])
                            ->action(
                                Action::make('lihatFotoNota')
                                    ->label('Lihat Foto')
                                    ->modalHeading('Foto Nota')
                                    ->modalWidth(Width::TwoExtraLarge)
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('Tutup')
                                    ->visible(fn($record) => filled($record->foto))
                                    ->modalContent(function ($record) {
                                        $paths = is_array($record->foto) ? $record->foto : [$record->foto];
                                        $paths = array_filter($paths);

                                        if (blank($paths)) {
                                            return new HtmlString('<p class="text-sm text-gray-500">Foto tidak ditemukan.</p>');
                                        }

                                        $html = '<div class="grid grid-cols-2 sm:grid-cols-3 gap-3">';
                                        foreach ($paths as $path) {
                                            $url = Storage::disk('public')->url($path);
                                            $url = preg_replace('#(?<!:)//+#', '/', $url);
                                            $html .= "<a href=\"{$url}\" target=\"_blank\" class=\"block\">"
                                                . "<img src=\"{$url}\" alt=\"Foto nota\" class=\"w-full h-40 object-cover rounded-lg border border-gray-200 dark:border-gray-700 hover:opacity-80 transition\" />"
                                                . "</a>";
                                        }
                                        $html .= '</div>';

                                        return new HtmlString($html);
                                    })
                            ),
                    ]),
            ]);
    }
}