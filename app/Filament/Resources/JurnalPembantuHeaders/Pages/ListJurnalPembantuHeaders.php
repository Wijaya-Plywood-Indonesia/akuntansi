<?php

namespace App\Filament\Resources\JurnalPembantuHeaders\Pages;

use App\Filament\Resources\JurnalPembantuHeaders\JurnalPembantuHeaderResource;
use App\Services\ImportJurnalProduksiService;
use Filament\Actions\Action;
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
                        // Menggunakan HtmlString agar tampilan panduan lebih rapi di modal
                        ->content(new HtmlString('
                            <div class="text-sm text-gray-500">
                                <strong class="text-gray-700 dark:text-gray-300">📋 Panduan:</strong>
                                <ul class="list-disc pl-5 mt-1">
                                    <li>File harus berformat <strong>.xlsx</strong></li>
                                    <li>Harus ada sheet bernama <strong>"jurnal produksi"</strong></li>
                                    <li>Format kolom: <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">Nama Akun | tgl | jurnal | No Akun | No | mm | Nama | Keterangan | map | hit kbk | Banyak | M3 | Harga | Total</code></li>
                                    <li>Data yang sudah pernah diimport (berdasarkan No. Jurnal) akan dilewati otomatis</li>
                                </ul>
                            </div>
                        ')),

                    FileUpload::make('file_excel')
                        ->label('File Excel (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240)
                        ->required()
                        ->disk('local')
                        // Biarkan Filament yang menangani penyimpanannya secara rapi
                        ->directory('imports/jurnal-produksi'),
                ])
                ->action(function (array $data) {
                    $filePath = $data['file_excel'] ?? null;

                    if (!$filePath) {
                        Notification::make()->danger()->title('File tidak ditemukan')->send();
                        return;
                    }

                    $disk = Storage::disk('local');
                    $fullPath = $disk->path($filePath);

                    try {
                        // Eksekusi Service
                        $service = app(ImportJurnalProduksiService::class);
                        $result  = $service->import($fullPath, Auth::id());

                        if ($result['success']) {
                            $jumlahJurnal = count($result['results']);
                            $detail = collect($result['results'])
                                ->map(fn($r) => "• {$r['no_dokumen']} ({$r['jumlah_baris']} baris)")
                                ->join("\n");

                            Notification::make()
                                ->success()
                                ->title("Import Berhasil — {$jumlahJurnal} jurnal diimport")
                                ->body($detail)
                                ->persistent()
                                ->send();

                        } else {
                            $errorMsg = implode("\n", $result['errors']);

                            if (!empty($result['results'])) {
                                $jumlahJurnal = count($result['results']);
                                Notification::make()
                                    ->warning()
                                    ->title("Import Sebagian — {$jumlahJurnal} berhasil, ada peringatan")
                                    ->body($errorMsg)
                                    ->persistent()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title('Import Gagal')
                                    ->body($errorMsg)
                                    ->persistent()
                                    ->send();
                            }
                        }

                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Terjadi Kesalahan Sistem')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    } finally {
                        // Pastikan file SELALU dihapus setelah selesai (baik sukses maupun error)
                        // agar storage server tidak penuh oleh file sampah Excel
                        if ($disk->exists($filePath)) {
                            $disk->delete($filePath);
                        }
                    }

                    // Muat ulang halaman untuk melihat data baru
                    return redirect(request()->header('Referer') ?? static::getResource()::getUrl());
                }),

            CreateAction::make(),
        ];
    }
}