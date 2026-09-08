<?php

namespace App\Filament\Resources\JurnalPembantuHeaders\Pages;

use App\Filament\Resources\JurnalPembantuHeaders\JurnalPembantuHeaderResource;
use App\Services\PostingJurnalPembantuService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ListJurnalPembantuHeaders extends ListRecords
{
    protected static string $resource = JurnalPembantuHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ════════════════════════════════════════════════════════
            // TOMBOL POSTING BATCH (1 HARI, 7 HARI, 1 BULAN)
            // ════════════════════════════════════════════════════════
            ActionGroup::make([
                Action::make('posting_1_hari')
                    ->label('Posting 1 Hari (Hari Ini)')
                    ->icon('heroicon-o-calendar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Posting Jurnal Pembantu 1 Hari (Hari Ini)')
                    ->modalDescription('Semua jurnal pembantu berstatus Draft pada hari ini akan diposting ke Jurnal Umum, berurutan dari nomor jurnal terkecil ke terbesar. Lanjutkan?')
                    ->modalSubmitActionLabel('Ya, Posting Hari Ini')
                    ->action(fn () => $this->jalankanPostingBatch(1, '1 Hari (Hari Ini)')),

                Action::make('posting_7_hari')
                    ->label('Posting 7 Hari Terakhir')
                    ->icon('heroicon-o-calendar-days')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Posting Jurnal Pembantu 7 Hari Terakhir')
                    ->modalDescription('Semua jurnal pembantu berstatus Draft dalam 7 hari terakhir akan diposting ke Jurnal Umum, berurutan dari nomor jurnal terkecil ke terbesar. Lanjutkan?')
                    ->modalSubmitActionLabel('Ya, Posting 7 Hari')
                    ->action(fn () => $this->jalankanPostingBatch(7, '7 Hari Terakhir')),

                Action::make('posting_1_bulan')
                    ->label('Posting 1 Bulan Terakhir (30 Hari)')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Posting Jurnal Pembantu 1 Bulan Terakhir (30 Hari)')
                    ->modalDescription('Semua jurnal pembantu berstatus Draft dalam 30 hari terakhir akan diposting ke Jurnal Umum, berurutan dari nomor jurnal terkecil ke terbesar. Lanjutkan?')
                    ->modalSubmitActionLabel('Ya, Posting 1 Bulan')
                    ->action(fn () => $this->jalankanPostingBatch(30, '1 Bulan Terakhir')),
            ])
                ->label('Posting Jurnal')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->button(),

            // ════════════════════════════════════════════════════════
            // TOMBOL IMPORT JURNAL PRODUKSI
            // ════════════════════════════════════════════════════════
            Action::make('import_jurnal_produksi')
                ->label('Import Jurnal Produksi')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->modalHeading('Import Jurnal Produksi dari Excel')
                ->modalDescription('Upload file Excel (.xlsx) hasil export dari sistem produksi. Pastikan file mengandung sheet "jurnal produksi".')
                ->modalWidth('lg')
                ->modalSubmitActionLabel('Import Sekarang')
                ->form([
                    Placeholder::make('panduan')
                        ->label('')
                        ->content(new HtmlString('
                            <div class="text-sm text-gray-500">
                                <strong class="text-gray-700 dark:text-gray-300">📋 Panduan:</strong>
                                <ul class="list-disc pl-5 mt-1">
                                    <li>File harus berformat <strong>.xlsx</strong></li>
                                    <li>Harus ada sheet bernama <strong>"jurnal produksi"</strong></li>
                                    <li>Format kolom: <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">Nama Akun | tgl | jurnal | No Akun | No | mm | Nama | Keterangan | map | hit kbk | Banyak | M3 | Harga | Total</code></li>
                                    <li>Data yang sudah pernah diimport (berdasarkan No. Jurnal / nama file) akan dilewati otomatis</li>
                                </ul>
                            </div>
                        ')),

                    FileUpload::make('file_excel')
                        ->label('File Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240)
                        ->required()
                        ->disk('local')
                        ->directory('imports/jurnal-produksi'),
                ])
                ->action(function (array $data) {
                    $filePath = $data['file_excel'] ?? null;

                    if (! $filePath) {
                        Notification::make()->danger()->title('File tidak ditemukan')->send();

                        return;
                    }

                    $disk = Storage::disk('local');
                    $fullPath = $disk->path($filePath);

                    try {
                        $service = app(ImportJurnalProduksiService::class);
                        $result = $service->import($fullPath, Auth::id());

                        $jumlahJurnal = count($result['results']);

                        if ($jumlahJurnal > 0) {
                            $detail = collect($result['results'])
                                ->map(fn ($r) => "• {$r['no_dokumen']} ({$r['jumlah_baris']} baris) → ".implode(', ', $r['headers']))
                                ->join("\n");

                            if (empty($result['errors'])) {
                                Notification::make()
                                    ->success()
                                    ->title("Import Berhasil — {$jumlahJurnal} jurnal diimport")
                                    ->body($detail)
                                    ->persistent()
                                    ->send();
                            } else {
                                $errorMsg = implode("\n", $result['errors']);
                                Notification::make()
                                    ->warning()
                                    ->title("Import Berhasil dengan Peringatan — {$jumlahJurnal} jurnal diimport")
                                    ->body($detail."\n\n⚠ Peringatan:\n".$errorMsg)
                                    ->persistent()
                                    ->send();
                            }
                        } else {
                            $errorMsg = ! empty($result['errors'])
                                ? implode("\n", $result['errors'])
                                : 'Tidak ada data jurnal yang berhasil diimport.';

                            Notification::make()
                                ->danger()
                                ->title('Import Gagal')
                                ->body($errorMsg)
                                ->persistent()
                                ->send();
                        }

                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Terjadi Kesalahan Sistem')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    } finally {
                        if ($disk->exists($filePath)) {
                            $disk->delete($filePath);
                        }
                    }

                    return redirect(request()->header('Referer') ?? static::getResource()::getUrl());
                }),

            CreateAction::make(),
        ];
    }

    /**
     * Eksekusi posting batch berdasarkan jumlah hari dan tampilkan notifikasi hasil
     */
    protected function jalankanPostingBatch(int $jumlahHari, string $labelPeriode): void
    {
        try {
            $service = app(PostingJurnalPembantuService::class);
            $result = $service->postingBatchPeriode($jumlahHari, now(), Auth::id());

            if ($result['total_jurnal'] === 0) {
                Notification::make()
                    ->info()
                    ->title('Tidak Ada Data Draft')
                    ->body("Tidak ditemukan jurnal pembantu berstatus Draft pada periode {$labelPeriode}.")
                    ->send();

                return;
            }

            if ($result['gagal'] === 0) {
                Notification::make()
                    ->success()
                    ->title("Posting Selesai ({$labelPeriode})")
                    ->body("Berhasil memposting {$result['sukses']} nomor jurnal ke Jurnal Umum sesuai urutan nomor jurnal terkecil.")
                    ->persistent()
                    ->send();
            } else {
                $errorDetails = implode("\n", array_slice($result['errors'], 0, 5));
                if (count($result['errors']) > 5) {
                    $sisa = count($result['errors']) - 5;
                    $errorDetails .= "\n... dan {$sisa} error lainnya.";
                }

                Notification::make()
                    ->warning()
                    ->title("Posting Selesai dengan Sebagian Gagal ({$labelPeriode})")
                    ->body("Berhasil: {$result['sukses']} jurnal. Gagal: {$result['gagal']} jurnal.\n\nRincian error:\n{$errorDetails}")
                    ->persistent()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Melakukan Posting Masal')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }
}
