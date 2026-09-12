<?php

namespace App\Filament\Resources\JurnalPembantuHeaders\Tables;

use App\Models\JurnalLampiran;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalUmum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;

class JurnalPembantuHeadersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_jurnal_pembantu')
                    ->label('No. JP')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tgl_transaksi')
                    ->label('Tgl. Transaksi')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('jurnal')
                    ->label('No. Jurnal')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('jenis_transaksi')
                    ->label('Jenis')
                    ->formatStateUsing(
                        fn($state) =>
                        JurnalPembantuHeader::JENIS[$state] ?? $state
                    )
                    ->sortable(),

                ImageColumn::make('lampiran.paths')
                    ->label('Foto')
                    ->disk('public')
                    ->stacked()
                    ->limit(3)
                    ->limitedRemainingText()
                    ->circular(false)
                    ->size(32)
                    ->extraImgAttributes(['class' => 'cursor-pointer hover:opacity-75 transition'])
                    ->action(
                        Action::make('lihatFotoLampiranJP')
                            ->label('Lihat Foto')
                            ->modalHeading(fn($record) => 'Foto Lampiran — No. Jurnal ' . $record->jurnal)
                            ->modalWidth(Width::TwoExtraLarge)
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Tutup')
                            ->visible(fn($record) => filled($record->lampiran?->paths))
                            ->modalContent(function ($record) {
                                $paths = $record->lampiran?->paths ?? [];

                                if (blank($paths)) {
                                    return new HtmlString('<p class="text-sm text-gray-500">Belum ada foto lampiran.</p>');
                                }

                                $html = '<div class="grid grid-cols-2 sm:grid-cols-3 gap-3">';
                                foreach ($paths as $path) {
                                    $url = Storage::disk('public')->url($path);
                                    $url = preg_replace('#(?<!:)//+#', '/', $url);
                                    $html .= "<a href=\"{$url}\" target=\"_blank\" class=\"block\">"
                                        . "<img src=\"{$url}\" alt=\"Foto lampiran\" class=\"w-full h-40 object-cover rounded-lg border border-gray-200 dark:border-gray-700 hover:opacity-80 transition\" />"
                                        . "</a>";
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                    ),

                TextColumn::make('no_akun')
                    ->label('Akun')
                    ->searchable(),

                TextColumn::make('nama_akun')
                    ->label('Nama Akun')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('keterangan')
                    ->limit(100),

                TextColumn::make('map')
                    ->label('D/K')
                    ->badge()
                    ->color(
                        fn($state) =>
                        strtolower($state) === 'd'
                            ? 'info'
                            : 'warning'
                    )
                    ->formatStateUsing(
                        fn($state) => strtoupper($state)
                    ),

                TextColumn::make('total_nilai')
                    ->label('Total Nilai')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'draft' => 'gray',
                        'diposting' => 'success',
                        'dibalik' => 'warning',
                        'dibatalkan' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(
                        fn($state) =>
                        JurnalPembantuHeader::STATUSES[$state] ?? $state
                    ),

                TextColumn::make('no_dokumen')
                    ->label('No. Dokumen')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('dibuatOleh.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(JurnalPembantuHeader::STATUSES),

                SelectFilter::make('jenis_transaksi')
                    ->label('Jenis Transaksi')
                    ->options(JurnalPembantuHeader::JENIS),

                SelectFilter::make('map')
                    ->label('Posisi D/K')
                    ->options(JurnalPembantuHeader::MAP),

                Filter::make('tgl_transaksi')
                    ->form([
                        DatePicker::make('dari')
                            ->label('Dari Tanggal'),

                        DatePicker::make('sampai')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(
                        fn(Builder $query, array $data): Builder =>
                        $query
                            ->when(
                                $data['dari'] ?? null,
                                fn($q, $v) =>
                                $q->whereDate('tgl_transaksi', '>=', $v)
                            )
                            ->when(
                                $data['sampai'] ?? null,
                                fn($q, $v) =>
                                $q->whereDate('tgl_transaksi', '<=', $v)
                            )
                    ),
            ])
            ->actions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(
                        fn($record) =>
                        $record->isDraft()
                    ),

                Action::make('posting')
                    ->label(
                        fn($record) =>
                        "Posting Jurnal No. {$record->jurnal}"
                    )
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->visible(function ($record) {
                        if (!$record->isDraft()) {
                            return false;
                        }

                        $idPertama = JurnalPembantuHeader::query()
                            ->where('jurnal', $record->jurnal)
                            ->where('status', JurnalPembantuHeader::STATUS_DRAFT)
                            ->min('id');

                        return (int) $record->id === (int) $idPertama;
                    })
                    ->requiresConfirmation()
                    ->modalHeading(
                        fn($record) =>
                        "Posting Jurnal No. {$record->jurnal}"
                    )
                    ->modalDescription(function ($record) {
                        $headers = JurnalPembantuHeader::query()
                            ->where('jurnal', $record->jurnal)
                            ->get();

                        $totalD = $headers->where('map', 'd')->sum('total_nilai');
                        $totalK = $headers->where('map', 'k')->sum('total_nilai');

                        $balance = abs($totalD - $totalK) < 0.0001;

                        return
                            "Jumlah Baris: {$headers->count()} | " .
                            "Debit: Rp " . number_format($totalD, 0, ',', '.') . " | " .
                            "Kredit: Rp " . number_format($totalK, 0, ',', '.') . " | " .
                            "Status: " . ($balance ? '✓ Balance' : '✗ Tidak Balance');
                    })
                    ->modalSubmitActionLabel('Ya, Posting')
                    ->action(function ($record) {
                        try {
                            app(\App\Services\PostingJurnalPembantuService::class)->postingByNomorJurnal($record->jurnal, Auth::id());

                            Notification::make()
                                ->success()
                                ->title('Berhasil Diposting')
                                ->body('Jurnal berhasil diposting.')
                                ->send();
                        } catch (Throwable $e) {
                            report($e);
                            Notification::make()
                                ->danger()
                                ->title('Gagal Posting')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Action::make('balik')
                    ->label('Jurnal Balik')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(function ($record) {
                        if (!$record->isPosted() || $record->adalah_jurnal_balik) {
                            return false;
                        }

                        $idPertama = JurnalPembantuHeader::query()
                            ->where('jurnal', $record->jurnal)
                            ->where('status', JurnalPembantuHeader::STATUS_DIPOSTING)
                            ->min('id');

                        return (int) $record->id === (int) $idPertama;
                    })
                    ->requiresConfirmation()
                    ->modalHeading(
                        fn($record) =>
                        "Buat Jurnal Balik No. {$record->jurnal}"
                    )
                    ->modalSubmitActionLabel('Ya, Buat')
                    ->action(function ($record) {
                        try {
                            DB::transaction(function () use ($record) {
                                // FIX: sama seperti action "posting" di atas — urutkan
                                // Debit dulu baru Kredit supaya jurnal balik yang
                                // dihasilkan juga konsisten urutannya.
                                $headers = JurnalPembantuHeader::query()
                                    ->with([
                                        'items' => fn($q) => $q->where('status', true)
                                    ])
                                    ->where('jurnal', $record->jurnal)
                                    ->where('status', JurnalPembantuHeader::STATUS_DIPOSTING)
                                    ->orderBy('map')
                                    ->orderBy('id')
                                    ->lockForUpdate()
                                    ->get();

                                if ($headers->isEmpty()) {
                                    throw new \Exception('Data posting tidak ditemukan.');
                                }

                                $sudahDibalik = $headers
                                    ->where('status', JurnalPembantuHeader::STATUS_DIBALIK)
                                    ->count();

                                if ($sudahDibalik > 0) {
                                    throw new \Exception('Jurnal sudah dibalik sebelumnya.');
                                }

                                $maxJurnal = (int) (JurnalPembantuHeader::query()->lockForUpdate()->max('jurnal') ?? 0);
                                $maxNoJP = (int) (JurnalPembantuHeader::query()->lockForUpdate()->max('no_jurnal_pembantu') ?? 0);

                                $nomorJurnalBaru = $maxJurnal + 1;
                                $noJPBaru = $maxNoJP + 1;

                                foreach ($headers as $header) {
                                    $headerBaru = JurnalPembantuHeader::create([
                                        'no_jurnal_pembantu' => $noJPBaru++,
                                        'tgl_transaksi' => now()->format('Y-m-d'),
                                        'jenis_transaksi' => 'balik',
                                        'modul_asal' => $header->modul_asal,
                                        'jurnal' => $nomorJurnalBaru,
                                        'no_akun' => $header->no_akun,
                                        'nama_akun' => $header->nama_akun,
                                        // Jurnal balik SENGAJA membalik posisi D<->K, jadi
                                        // kalau baris asli sudah D,D,K,K, hasil baliknya
                                        // otomatis jadi K,K,D,D dalam urutan insert yang
                                        // sama — TIDAK di-re-sort di sini, karena listing
                                        // & posting toh sudah orderBy('map') sendiri.
                                        'map' => strtolower($header->map) === 'd' ? 'k' : 'd',
                                        'keterangan' => 'BALIK: ' . $header->keterangan,
                                        'no_dokumen' => $header->no_dokumen,
                                        'total_nilai' => $header->total_nilai,
                                        'status' => JurnalPembantuHeader::STATUS_DRAFT,
                                        'adalah_jurnal_balik' => true,
                                        'membalik_id' => $header->id,
                                        'dibuat_oleh' => Auth::id() ?? 1,
                                    ]);

                                    foreach ($header->items as $item) {
                                        $headerBaru->items()->create([
                                            'urut' => $item->urut,
                                            'jenis_pihak' => $item->jenis_pihak,
                                            'nama_pihak' => $item->nama_pihak,
                                            'nama_barang' => $item->nama_barang,
                                            'no_dokumen' => $item->no_dokumen,
                                            'no_referensi' => $item->no_referensi,
                                            'keterangan' => $item->keterangan,
                                            'banyak' => $item->banyak,
                                            'm3' => $item->m3,
                                            'harga' => $item->harga,
                                            'jumlah' => $item->jumlah,
                                            'shadow_harga' => $item->shadow_harga ?? $item->harga,
                                            'shadow_jumlah' => $item->shadow_jumlah ?? $item->jumlah,
                                            'hit_kbk' => $item->hit_kbk,
                                            'status' => true,
                                            'created_by' => Auth::id() ?? 1,
                                        ]);
                                    }

                                    $header->update([
                                        'status' => JurnalPembantuHeader::STATUS_DIBALIK,
                                    ]);
                                }
                            }, 5);

                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body('Jurnal balik berhasil dibuat.')
                                ->send();
                        } catch (Throwable $e) {
                            report($e);
                            Notification::make()
                                ->danger()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                DeleteAction::make()
                    ->visible(
                        fn($record) =>
                        $record->isDraft()
                    ),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            // FIX: sebelumnya defaultSort('created_at', 'desc') — ini bikin
            // baris yang dibuat BELAKANGAN (mis. baris K yang di-loop setelah
            // baris D) tampil DI ATAS baris yang dibuat duluan, jadi dalam 1
            // No. Jurnal urutannya kebalik/acak (K, K, D, D alih-alih D, D, K, K).
            //
            // Sekarang: urutkan per grup 'jurnal' (nomor jurnal terbaru di atas,
            // supaya transaksi baru tetap gampang ditemukan), lalu DI DALAM
            // grup jurnal yang sama, urutkan 'map' (d sebelum k, alfabetis)
            // dan 'id' (stabil, sesuai urutan insert asli) sebagai tie-breaker.
            ->modifyQueryUsing(
                fn(Builder $query) =>
                $query
                    ->with('lampiran')
                    ->orderByDesc('jurnal')
                    ->orderBy('map')
                    ->orderBy('id')
            );
    }
}