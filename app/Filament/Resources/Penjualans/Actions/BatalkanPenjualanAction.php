<?php

namespace App\Filament\Resources\Penjualans\Actions;

use App\Models\Penjualan;
use App\Services\PenjualanPembatalanService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Tombol "Batalkan Penjualan". Dipakai di halaman View Penjualan dan di tabel
 * daftar Penjualan. Bisa dipakai semua user yang bisa melihat tombolnya
 * (tidak dibatasi super admin), alasan wajib diisi.
 */
class BatalkanPenjualanAction
{
    public static function make(): Action
    {
        return Action::make('batalkan_penjualan')
            ->label('Batalkan Penjualan')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn ($record) => $record instanceof Penjualan
                && app(PenjualanPembatalanService::class)->bisaDibatalkan($record))
            ->requiresConfirmation()
            ->modalHeading(fn ($record) => 'Batalkan Nota '.$record->no_nota.'?')
            ->modalDescription(
                'Jurnal nota ini akan dibalik otomatis dan nota ditandai DIBATALKAN, sehingga hilang dari menu '
                .'Pelunasan dan laporan penjualan. Nota tidak dihapus. Tindakan ini tidak bisa dibatalkan.'
            )
            ->modalSubmitActionLabel('Ya, Batalkan')
            ->form([
                Textarea::make('alasan')
                    ->label('Alasan pembatalan')
                    ->placeholder('Contoh: salah input, barang tidak jadi dikirim...')
                    ->required()
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function ($record, array $data) {
                try {
                    $hasil = app(PenjualanPembatalanService::class)
                        ->batalkan($record, (int) filament()->auth()->id(), (string) $data['alasan']);
                } catch (InvalidArgumentException|RuntimeException $e) {
                    Notification::make()
                        ->title('Nota tidak bisa dibatalkan')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Gagal membatalkan nota')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                $info = $hasil['dibalik'] > 0
                    ? 'Jurnal balik No. '.implode(', ', $hasil['jurnal_balik']).' sudah diposting.'
                    : 'Tidak ada jurnal baru yang perlu dibuat (jurnal sebelumnya sudah dibalik atau belum diposting).';

                Notification::make()
                    ->title('Nota '.$record->no_nota.' dibatalkan')
                    ->body($info)
                    ->success()
                    ->send();
            });
    }
}