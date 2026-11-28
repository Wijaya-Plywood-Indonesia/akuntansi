<?php

namespace App\Filament\Resources\Penjualans\Tables;

use App\Exports\PenjualanExport;
use App\Filament\Resources\Penjualans\Actions\BatalkanPenjualanAction;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PenjualansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByRaw('validated_by IS NOT NULL ASC'))
            ->columns([
                TextColumn::make('no_nota')
                    ->label('No. SJ12')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status_transaksi')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'LUNAS' => 'success',
                        'COD' => 'warning',
                        'PENDING' => 'gray',
                        'BELUM DIBAYAR' => 'danger',
                        'DIBATALKAN' => 'danger',
                        default => 'secondary',
                    })
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Kasir')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('validator.name')
                    ->label('Validator')
                    ->sortable()
                    ->searchable()
                    ->placeholder('Belum Divalidasi') // Menampilkan teks jika belum ada yang validasi
                    ->badge() // Opsional: menjadikannya badge agar lebih menonjol
                    ->color(fn ($state) => $state ? 'success' : 'gray') // Warna hijau jika ada validator, abu-abu jika belum
                    ->toggleable(),

                TextColumn::make('tanggal')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->locale('id')->translatedFormat('j F Y') : null)
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->placeholder('kosong')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('keterangan_pembayaran')
                    ->placeholder('kosong')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('nama_customer')
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("TRIM(LOWER(nama_customer)) {$direction}"))
                    ->searchable()
                    ->placeholder('Tidak Dicatat'),

                TextColumn::make('metode_pembayaran')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total')
                    ->label('Total Pembelian')
                    ->money('IDR', locale: 'id_ID')
                    ->sortable(),
            ])

            ->defaultSort('no_nota', 'desc')

            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(function ($record) {
                        if ($record->status_transaksi === 'DIBATALKAN') {
                            return false;
                        }

                        $user = filament()->auth()->user();
                        if ($user->hasRole('super_admin')) {
                            return true;
                        }

                        return empty($record->validated_by);
                    }),

                // 🖨 CETAK
                Action::make('cetak')
                    ->label('Cetak Nota')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->url(fn ($record) => route('nota.cetak', $record))
                    ->openUrlInNewTab()
                    ->visible(
                        // fn($record) =>
                        // !empty($record->validated_by)
                        //     && in_array($record->status_transaksi, [
                        //         'LUNAS',
                        //         'COD',
                        //         'PENDING',
                        //     ]) // ✅ Syarat baru: muncul jika status ada di dalam daftar ini
                    ),

                Action::make('cetakThermal')
                    ->label('Cetak Thermal')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->url(fn ($record) => route('nota.cetakThermal', $record))
                    ->openUrlInNewTab()
                    ->visible(
                        fn ($record) => ! empty($record->validated_by)
                            && in_array($record->status_transaksi, [
                                'LUNAS',
                                'COD',
                                'PENDING',
                            ])
                    ),

                Action::make('suratJalan')
                    ->label('Cetak Surat Jalan')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->url(fn ($record) => route('surat-jalan.cetak', $record))
                    ->openUrlInNewTab()
                    ->visible(
                        fn ($record) => ! empty($record->validated_by)
                            && in_array($record->status_transaksi, [
                                'LUNAS',
                                'COD',
                                'PENDING',
                            ])
                    ),

                Action::make('edit_keterangan')
                    ->label('Edit Keterangan')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->modalHeading('Edit Keterangan')
                    ->modalSubmitActionLabel('Simpan')
                    ->form([
                        TextInput::make('keterangan')
                            ->label('Keterangan')
                            ->placeholder('Masukkan keterangan...')
                            ->maxLength(255),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'keterangan' => $data['keterangan'],
                        ]);

                        Notification::make()
                            ->title('Keterangan berhasil diperbarui')
                            ->success()
                            ->send();
                    }),

                BatalkanPenjualanAction::make(),

                DeleteAction::make()
                    ->visible(function ($record) {
                        $user = filament()->auth()->user();
                        // Super Admin selalu bisa lihat tombol
                        if ($user->hasRole('super_admin')) {
                            return true;
                        }

                        // Staff hanya bisa lihat jika belum divalidasi
                        return empty($record->validated_by);
                    })
                    ->requiresConfirmation()
                    ->action(function ($record, DeleteAction $action) {

                        // 1. Cek relasi retur: jika sudah ada retur tidak boleh dihapus langsung
                        $adaReturn = $record->returns()->exists();

                        if ($adaReturn) {
                            Notification::make()
                                ->danger()
                                ->title('Data Gagal Dihapus!')
                                ->body("Nota {$record->no_nota} tidak dapat dihapus karena sudah terdapat data retur yang terikat pada nota ini. Silakan hapus data retur terlebih dahulu.")
                                ->persistent()
                                ->send();

                            // Menghentikan proses penghapusan
                            $action->halt();
                        }

                        // 2. Hapus detail barang & record penjualan dalam database transaction
                        DB::transaction(function () use ($record) {
                            $record->details()->delete();
                            $record->delete();
                        });

                        Notification::make()
                            ->success()
                            ->title('Berhasil Dihapus')
                            ->body("Data penjualan Nota {$record->no_nota} beserta rincian item telah berhasil dihapus dari sistem.")
                            ->send();
                    }),
            ])
            ->headerActions([
                Action::make('exportExcel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->modalHeading('Export Penjualan ke Excel')
                    ->modalDescription('Hanya data penjualan yang SUDAH DIVALIDASI yang akan diexport. Pilih rentang tanggal transaksi (kosongkan jika ingin export seluruh data).')
                    ->modalSubmitActionLabel('Export')
                    ->form([
                        Grid::make(2)->schema([
                            DatePicker::make('tgl_dari')
                                ->label('Dari Tanggal')
                                ->native(true),
                            DatePicker::make('tgl_sampai')
                                ->label('Sampai Tanggal')
                                ->native(true)
                                ->default(now()->format('Y-m-d')),
                        ]),
                    ])
                    ->action(function (array $data) {
                        $tglDari = $data['tgl_dari'] ?? null;
                        $tglSampai = $data['tgl_sampai'] ?? null;

                        $fileName = 'penjualan_tervalidasi';
                        if ($tglDari || $tglSampai) {
                            $fileName .= '_'.($tglDari ?: 'awal').'_sd_'.($tglSampai ?: 'akhir');
                        }
                        $fileName .= '.xlsx';

                        return Excel::download(
                            new PenjualanExport($tglDari, $tglSampai),
                            $fileName
                        );
                    }),
            ])
            ->toolbarActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make(),
                // ]),
            ]);
    }
}
