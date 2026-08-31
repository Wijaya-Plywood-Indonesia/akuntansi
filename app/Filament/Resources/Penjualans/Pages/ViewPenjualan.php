<?php

namespace App\Filament\Resources\Penjualans\Pages;

use App\Filament\Resources\Penjualans\PenjualanResource;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalUmum;
use App\Models\Penjualan;
use App\Services\JurnalBalikService;
use App\Services\JurnalPenjualanTriplekService;
use App\Services\Penjualans\SyncPenjualanService;
use App\Services\StokPenyesuaianService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ViewPenjualan extends ViewRecord
{
    protected static string $resource = PenjualanResource::class;

    /**
     * Mapping jenis_transaksi (dipilih di POS) -> status_transaksi otomatis.
     * COD & DP sama-sama berujung PIUTANG karena JurnalPenjualanTriplekService
     * sudah menangani proporsi Kas vs Piutang dari field $penjualan->bayar,
     * jadi status akhirnya cukup dibedakan LUNAS vs PIUTANG saja.
     */
    public static function statusDariJenisTransaksi(?string $jenisTransaksi): string
    {
        return match ($jenisTransaksi) {
            'BAYAR_DIMUKA' => 'LUNAS',
            'COD', 'DP' => 'PIUTANG',
            default => 'PIUTANG',
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            // ✅ ACTION: VALIDASI TRANSAKSI
            Action::make('validasi_transaksi')
                ->label('Validasi Transaksi')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(
                    fn ($record) => $record->status_transaksi !== 'LUNAS'
                )
                ->disabled(
                    fn ($record) => $record->user_id === filament()->auth()->id()
                        && ! filament()->auth()->user()->hasRole('super_admin')
                )
                ->modalHeading('Validasi Transaksi')
                ->modalSubmitActionLabel('Simpan Validasi')
                ->form(fn ($record) => [
                    TextInput::make('validator_name')
                        ->label('Validator')
                        ->default(fn () => filament()->auth()->user()->name)
                        ->disabled()
                        ->dehydrated(false),

                    Placeholder::make('status_preview')
                        ->label('Status Transaksi (otomatis)')
                        ->content(function () use ($record) {
                            $status = self::statusDariJenisTransaksi($record->jenis_transaksi);
                            $keterangan = match ($record->jenis_transaksi) {
                                'COD' => 'COD — belum ada uang masuk, seluruh nilai jadi Piutang.',
                                'DP' => 'DP — sebagian sudah dibayar (Rp ' . number_format($record->bayar) . '), sisanya jadi Piutang.',
                                'BAYAR_DIMUKA' => 'Bayar Dimuka — sudah lunas penuh, langsung jadi LUNAS.',
                                default => 'Jenis transaksi tidak dikenali, cek data POS.',
                            };

                            return new \Illuminate\Support\HtmlString(
                                "<span class='font-bold'>{$status}</span><br><span class='text-xs text-gray-400'>{$keterangan}</span>"
                            );
                        }),
                ])
                ->action(function ($record, array $data) {
                    if (
                        $record->user_id === filament()->auth()->id()
                        && ! filament()->auth()->user()->hasRole('super_admin')
                    ) {
                        Notification::make()
                            ->title('Tidak boleh validasi transaksi sendiri')
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($record->status_transaksi === 'LUNAS') {
                        Notification::make()
                            ->title('Transaksi sudah lunas dan final')
                            ->warning()
                            ->send();

                        return;
                    }

                    // Status TIDAK lagi dipilih manual — otomatis mengikuti
                    // jenis_transaksi yang sudah ditentukan sejak di POS.
                    $statusBaru = self::statusDariJenisTransaksi($record->jenis_transaksi);

                    $validatorId = filament()->auth()->id();

                    try {
                        DB::transaction(function () use ($record, $statusBaru, $validatorId) {
                            // Modul ini khusus triplek & turunannya (telur
                            // punya web sendiri). Baik status akhirnya PIUTANG
                            // maupun LUNAS, sama-sama lewat service terpadu ini
                            // — bedanya cuma proporsi Kas vs Piutang, yang
                            // sudah dihitung otomatis dari $record->bayar.
                            app(StokPenyesuaianService::class)
                                ->lunas($record->id);

                            app(JurnalPenjualanTriplekService::class)
                                ->buatJurnalPenjualan($record, $validatorId);

                            $record->update([
                                'validated_by' => $validatorId,
                                'status_transaksi' => $statusBaru,
                            ]);
                        });
                    } catch (Throwable $e) {
                        Log::error('[ViewPenjualan::validasi_transaksi] Gagal memvalidasi transaksi', [
                            'penjualan_id' => $record->id,
                            'no_nota' => $record->no_nota ?? null,
                            'status_baru' => $statusBaru,
                            'validator_id' => $validatorId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        Notification::make()
                            ->title('Gagal Validasi Transaksi')
                            ->body('Terjadi kesalahan saat memproses validasi: '.$e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Transaksi berhasil divalidasi')
                        ->success()
                        ->send();
                }),

            // ❌ ACTION: BATAL VALIDASI (Termasuk Logika Auto-Post Jurnal Asli)
            Action::make('batal_validasi')
                ->label('Batal Validasi')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(
                    fn ($record) => ! empty($record->validated_by)
                        && filament()->auth()->user()->hasRole('super_admin')
                        && $record->status_transaksi === 'LUNAS'
                )
                ->action(function ($record) {
                    if (empty($record->validated_by)) {
                        Notification::make()
                            ->title('Transaksi belum divalidasi')
                            ->warning()
                            ->send();

                        return;
                    }

                    $userId = filament()->auth()->id();
                    $pesanNotif = 'Validasi telah dibatalkan.';

                    // FIX: Bungkus proses dalam try-catch agar error saat balik stok,
                    // posting jurnal asli, maupun pembuatan jurnal balik tertangkap rapi
                    // dan DB::transaction rollback otomatis, alih-alih meninggalkan
                    // data setengah jalan (mis. stok sudah dibalik tapi jurnal belum).
                    try {
                        DB::transaction(function () use ($record, $userId, &$pesanNotif) {
                            if ($record->status_transaksi === 'LUNAS') {
                                // 1. Balik stok
                                app(StokPenyesuaianService::class)
                                    ->batalLunas($record->id);

                                // 2. Logika Penyelamatan Jurnal Asli Jika Masih Draft
                                $headersAsli = JurnalPembantuHeader::where('no_dokumen', $record->no_nota)
                                    ->where('adalah_jurnal_balik', false)
                                    ->where('modul_asal', 'penjualan_telur') // Sesuai modul service Anda
                                    ->get();

                                $isMasihDraft = $headersAsli->contains(function ($header) {
                                    return $header->status === JurnalPembantuHeader::STATUS_DRAFT;
                                });

                                if ($isMasihDraft) {
                                    $nomorAsli = (int) $headersAsli->first()?->jurnal;
                                    $nomorFinal = $nomorAsli;

                                    // Geser nomor jika ternyata sudah dipakai
                                    if ($nomorAsli > 0 && JurnalUmum::where('jurnal', $nomorAsli)->exists()) {
                                        $nomorFinal = max(
                                            (int) (JurnalUmum::max('jurnal') ?? 0),
                                            (int) (JurnalPembantuHeader::max('jurnal') ?? 0)
                                        ) + 1;

                                        JurnalPembantuHeader::where('no_dokumen', $record->no_nota)
                                            ->where('adalah_jurnal_balik', false)
                                            ->where('modul_asal', 'penjualan_telur')
                                            ->update(['jurnal' => $nomorFinal]);
                                    }

                                    // Posting ke Jurnal Umum
                                    foreach ($headersAsli as $header) {
                                        $itemsAktif = $header->items()->where('status', true)->get();
                                        $totalBanyak = (float) $itemsAktif->sum('banyak');
                                        $totalM3 = (float) $itemsAktif->sum('m3');
                                        $totalNilaiHeader = (float) $header->total_nilai;

                                        $firstItem = $itemsAktif->first();
                                        $itemHitKbk = $firstItem?->hit_kbk;

                                        $hitKbk = '';
                                        $prefix = substr($header->no_akun, 0, 3);
                                        $isCashOrPayment = in_array($prefix, ['110', '111', '112', '113', '114', '210', '220', '230']);

                                        if (! $isCashOrPayment) {
                                            $hitKbk = 'b'; // default fallback

                                            if ($firstItem) {
                                                $b = (float) $firstItem->banyak;
                                                $m = (float) $firstItem->m3;
                                                $h = (float) $firstItem->harga;
                                                $j = (float) $firstItem->jumlah;

                                                if ($m > 0 && abs($j - ($m * $h)) < 0.01) {
                                                    $hitKbk = 'm';
                                                } elseif ($b > 0 && abs($j - ($b * $h)) < 0.01) {
                                                    $hitKbk = 'b';
                                                }
                                            }
                                        }

                                        if ($itemHitKbk === 'k') {
                                            $hitKbk = 'm';
                                        } elseif ($itemHitKbk === 'b') {
                                            $hitKbk = 'b';
                                        }

                                        if ($hitKbk === 'm') {
                                            $m3 = $totalM3;
                                            $banyak = $totalBanyak > 0 ? $totalBanyak : null;
                                            $harga = $totalM3 > 0 ? ($totalNilaiHeader / $totalM3) : $totalNilaiHeader;
                                        } elseif ($hitKbk === 'b') {
                                            $m3 = $totalM3 > 0 ? $totalM3 : null;
                                            $banyak = $totalBanyak > 0 ? $totalBanyak : 1;
                                            $harga = $totalBanyak > 0 ? ($totalNilaiHeader / $totalBanyak) : $totalNilaiHeader;
                                        } else {
                                            $m3 = $totalM3 > 0 ? $totalM3 : null;
                                            $banyak = $totalBanyak > 0 ? $totalBanyak : null;
                                            $harga = $totalNilaiHeader;
                                        }

                                        JurnalUmum::create([
                                            'tgl' => now()->format('Y-m-d'),
                                            'jurnal' => $nomorFinal,
                                            'no_akun' => $header->no_akun,
                                            'nama_akun' => $header->nama_akun,
                                            'nama' => $record->nama_customer ?? 'Pelanggan',
                                            'keterangan' => $header->keterangan.' (Otomatis Terposting karena Pembatalan)',
                                            'banyak' => $banyak !== null ? round($banyak, 4) : null,
                                            'm3' => $m3 !== null ? round($m3, 4) : null,
                                            'harga' => round($harga, 2),
                                            'hit_kbk' => $hitKbk,
                                            'map' => strtolower($header->map),
                                        ]);
                                    }

                                    $infoNomor = $nomorFinal !== $nomorAsli ? " (Nomor Jurnal disesuaikan menjadi No. {$nomorFinal} karena No. {$nomorAsli} sudah terpakai)" : '';
                                    $pesanNotif = "Jurnal Asli otomatis di-posting ke Jurnal Umum{$infoNomor}, dan ";
                                } else {
                                    $pesanNotif = '';
                                }

                                // 3. Buat jurnal balik otomatis
                                app(JurnalBalikService::class)
                                    ->buatJurnalBalikDariNota($record->no_nota, $userId);

                                $pesanNotif .= 'Jurnal Balik Baru berhasil diterbitkan di Jurnal Pembantu.';
                            }

                            // 4. Update status record penjualan
                            $record->update([
                                'validated_by' => null,
                                'status_transaksi' => 'BELUM DIBAYAR',
                            ]);
                        });
                    } catch (Throwable $e) {
                        Log::error('[ViewPenjualan::batal_validasi] Gagal membatalkan validasi transaksi', [
                            'penjualan_id' => $record->id,
                            'no_nota' => $record->no_nota ?? null,
                            'user_id' => $userId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        Notification::make()
                            ->title('Gagal Membatalkan Validasi')
                            ->body('Terjadi kesalahan saat memproses pembatalan: '.$e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Batal Validasi Berhasil')
                        ->body($pesanNotif)
                        ->warning()
                        ->send();
                }),

            Action::make('sinkronkan_data')
                ->label('Sinkronkan Data Penjualan')
                ->icon('heroicon-m-arrow-path')
                ->color('warning')
                ->modalWidth('lg')
                ->mountUsing(fn ($form, $record) => $form->fill([
                    'total_sebelum' => $record->total,
                    'total_saat_ini' => SyncPenjualanService::calculateCurrentTotal($record->id),
                    'bayar' => $record->bayar,
                    'kembalian' => $record->bayar - SyncPenjualanService::calculateCurrentTotal($record->id),
                    'keterangan' => $record->keterangan,
                ]))
                ->form([
                    TextInput::make('total_sebelum')
                        ->label('Total Sebelum')
                        ->numeric()
                        ->prefix('Rp')
                        ->dehydrated()
                        ->live(onBlur: true)
                        ->disabled(),

                    TextInput::make('total_saat_ini')
                        ->label('Total Saat Ini')
                        ->numeric()
                        ->prefix('Rp')
                        ->disabled()
                        ->dehydrated()
                        ->live(onBlur: true),

                    TextInput::make('bayar')
                        ->label('Bayar')
                        ->numeric()
                        ->prefix('Rp')
                        ->required()
                        ->live(onBlur: true)
                        ->dehydrated()
                        ->rules([
                            fn ($get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                $totalSaatIni = (float) $get('total_saat_ini');
                                $totalSebelum = (float) $get('total_sebelum');
                                $bayar = (float) $value;

                                if ($totalSebelum < $totalSaatIni && $bayar < $totalSaatIni) {
                                    $fail('Nominal pembayaran kurang. Minimal pembayaran adalah Rp '.number_format($totalSaatIni, 0, ',', '.'));
                                }
                            },
                        ])
                        ->afterStateUpdated(function ($state, $get, $set) {
                            $total_si = (float) $get('total_saat_ini');
                            $bayar = (float) $state;
                            $set('kembalian', $bayar - $total_si);
                        }),

                    TextInput::make('kembalian')
                        ->label('Kembalian')
                        ->numeric()
                        ->prefix('Rp')
                        ->disabled()
                        ->dehydrated()
                        ->formatStateUsing(fn ($state) => $state)
                        ->extraInputAttributes(['class' => 'text-xl font-bold text-success-600']),

                    TextInput::make('keterangan')
                        ->label('Keterangan'),
                ])
                ->action(function (array $data, $record) {
                    try {
                        $data['total'] = $data['total_saat_ini'];

                        // FIX: Langsung timpa dengan nilai final dari inputan form
                        // Jangan ditambah (+) dengan $record lama agar tidak dobel
                        $data['bayar'] = (float) ($data['bayar'] ?? 0);
                        $data['kembalian'] = (float) ($data['kembalian'] ?? 0);

                        SyncPenjualanService::syncPenjualan($record->id, $data);

                        Notification::make()
                            ->title('Data Berhasil Disinkronkan')
                            ->success()
                            ->send();

                        return redirect(request()->header('Referer'));
                    } catch (Throwable $e) {
                        Log::error('[ViewPenjualan::sinkronkan_data] Gagal sinkronisasi data penjualan', [
                            'penjualan_id' => $record->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        Notification::make()
                            ->title('Gagal Sinkronisasi')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}