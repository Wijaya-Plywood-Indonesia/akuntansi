<?php

namespace App\Filament\Resources\ReturnPenjualans\Tables;

use App\Services\JurnalReturnPenjualanService;
use App\Services\StokPenyesuaianService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class ReturnPenjualansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_retur')
                    ->label('No. Retur')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->description(fn($record) => "Ref Nota: {$record->no_nota}"),

                TextColumn::make('status_return')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'SELESAI'  => 'success',
                        'DITERIMA' => 'success',
                        'DIPROSES' => 'warning',
                        'PENDING'  => 'warning',
                        'DITOLAK'  => 'danger',
                        default    => 'secondary',
                    })
                    ->searchable(),

                TextColumn::make('jenis_retur')
                    ->label('Jenis Retur')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'NORMAL'      => 'success',
                        'SEBAGIAN'    => 'warning',
                        'DP_NORMAL'   => 'info',
                        'DP_SEBAGIAN' => 'primary',
                        default       => 'secondary',
                    })
                    ->formatStateUsing(fn(?string $state) => match ($state) {
                        'NORMAL'      => 'Normal',
                        'SEBAGIAN'    => 'Sebagian',
                        'DP_NORMAL'   => 'DP Normal',
                        'DP_SEBAGIAN' => 'DP Sebagian',
                        default       => $state ?: '-',
                    })
                    ->sortable(),

                TextColumn::make('bukuKitab.nama')
                    ->label('Buku Kitab')
                    ->placeholder(fn($record) => $record->kode_kitab ?: '-')
                    ->description(fn($record) => $record->kode_kitab)
                    ->toggleable(),

                TextColumn::make('bank')
                    ->label('Akun Pengembalian')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('nama_customer')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('Tidak Dicatat'),

                TextColumn::make('tanggal')
                    ->label('Tanggal Retur')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total Retur')
                    ->money('IDR', locale: 'id_ID')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('no_jurnal')
                    ->label('No. Jurnal')
                    ->badge()
                    ->color(fn($record) => $record->no_jurnal ? 'info' : 'gray')
                    ->formatStateUsing(fn($record) => $record->no_jurnal ? "JP #{$record->no_jurnal}" : '-')
                    ->url(fn($record) => $record->no_jurnal ? \App\Filament\Resources\JurnalPembantuHeaders\JurnalPembantuHeaderResource::getUrl('index', ['tableSearch' => $record->no_retur]) : null)
                    ->openUrlInNewTab()
                    ->sortable(false),

                TextColumn::make('details_return_sum_qty')
                    ->label('Qty')
                    ->sum('details_return', 'qty')
                    ->default(0)
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Petugas')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('validator.name')
                    ->label('Validator')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('validasi_retur')
                    ->label('Validasi & Jurnal')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn($record) => empty($record->validate_by) || empty($record->no_jurnal))
                    ->disabled(
                        fn($record) =>
                        $record->created_by === filament()->auth()->id()
                        && ! filament()->auth()->user()->hasRole(['super_admin', 'Akuntansi'])
                    )
                    ->modalHeading('Validasi Transaksi Retur & Terbitkan Jurnal')
                    ->modalSubmitActionLabel('Simpan Validasi')
                    ->form([
                        TextInput::make('validator_name')
                            ->label('Validator')
                            ->default(fn() => filament()->auth()->user()->name)
                            ->disabled()
                            ->dehydrated(false),

                        Select::make('status_return')
                            ->label('Status Retur')
                            ->options([
                                'DITERIMA' => 'DITERIMA (Masuk Jurnal)',
                                'SELESAI'  => 'SELESAI (Masuk Jurnal)',
                                'DIPROSES' => 'DIPROSES (Draft)',
                                'PENDING'  => 'PENDING (Draft)',
                                'DITOLAK'  => 'DITOLAK',
                            ])
                            ->default('DITERIMA')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        if (
                            $record->created_by === filament()->auth()->id()
                            && ! filament()->auth()->user()->hasRole(['super_admin', 'Akuntansi'])
                        ) {
                            Notification::make()
                                ->title('Anda tidak boleh memvalidasi retur yang Anda buat sendiri.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $status = $data['status_return'];
                        $validatorId = filament()->auth()->id() ?: 1;

                        try {
                            if (in_array($status, ['DITERIMA', 'SELESAI'])) {
                                // Penyesuaian stok masuk kembali
                                app(StokPenyesuaianService::class)->selesai($record->id);

                                // Pembuatan Jurnal Pembantu berbasis Buku Kitab
                                app(JurnalReturnPenjualanService::class)->buatJurnalReturn($record, $validatorId);
                            }

                            $record->update([
                                'validate_by'   => $validatorId,
                                'status_return' => $status,
                            ]);

                            $noJurnal = $record->fresh()->no_jurnal;
                            $infoJurnal = $noJurnal ? " Jurnal Pembantu Header diterbitkan (No. Jurnal #{$noJurnal})." : "";

                            Notification::make()
                                ->title('Status Retur berhasil divalidasi')
                                ->body("Retur {$record->no_retur} telah divalidasi.{$infoJurnal}")
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {
                            Log::error('[ReturnPenjualansTable::validasi] Gagal validasi retur', [
                                'return_id' => $record->id,
                                'error'     => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Gagal Memvalidasi Retur')
                                ->body('Terjadi kesalahan: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('lihat_jurnal')
                    ->label('Lihat Jurnal')
                    ->icon('heroicon-o-book-open')
                    ->color('info')
                    ->visible(fn($record) => ! empty($record->no_jurnal))
                    ->url(fn($record) => \App\Filament\Resources\JurnalPembantuHeaders\JurnalPembantuHeaderResource::getUrl('index', ['tableSearch' => $record->no_retur]))
                    ->openUrlInNewTab(),

                Action::make('batal_validasi')
                    ->label('Batal Validasi')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(
                        fn($record) =>
                        ! empty($record->validate_by) || ! empty($record->no_jurnal)
                    )
                    ->action(function ($record) {
                        try {
                            app(StokPenyesuaianService::class)->validasi_batal_dari_selesai($record->id);
                            app(JurnalReturnPenjualanService::class)->hapusJurnalReturn($record);

                            $record->update([
                                'validate_by'   => null,
                                'status_return' => 'DITOLAK',
                            ]);

                            Notification::make()
                                ->title('Validasi return berhasil dibatalkan')
                                ->body('Jurnal Pembantu terkait telah dibatalkan/dihapus.')
                                ->warning()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal Membatalkan Validasi')
                                ->body('Terjadi kesalahan: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                ViewAction::make(),

                Action::make('edit_keterangan')
                    ->label('Edit Catatan')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->modalHeading('Edit Catatan Retur')
                    ->modalSubmitActionLabel('Simpan')
                    ->form([
                        TextInput::make('keterangan')
                            ->label('Keterangan')
                            ->default(fn($record) => $record->keterangan)
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

                DeleteAction::make()
                    ->visible(fn($record) => filament()->auth()->user()->hasRole("super_admin")),
            ]);
    }
}
