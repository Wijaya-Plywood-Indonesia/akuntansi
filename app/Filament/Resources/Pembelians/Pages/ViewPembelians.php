<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Resources\Pembelians\PembeliansResource;
use App\Models\Pembelian;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use App\Services\JurnalPembelianTriplekService;
use App\Services\JurnalBalikService;
use Throwable;

class ViewPembelians extends ViewRecord
{
    protected static string $resource = PembeliansResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('validasi_pembelian')
                ->label('Validasi')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn(Pembelian $record) => empty($record->validated_by) && $record->status !== Pembelian::STATUS_BATAL)
                ->disabled(fn(Pembelian $record) => $record->created_by === filament()->auth()->id() && !filament()->auth()->user()->hasRole('super_admin'))
                ->modalHeading('Validasi Pembelian')
                ->modalSubmitActionLabel('Simpan Validasi')
                ->form(fn (Pembelian $record) => [
                    TextInput::make('validator_name')
                        ->label('Petugas Validasi')
                        ->default(fn() => filament()->auth()->user()->name)
                        ->disabled()
                        ->dehydrated(false),

                    // Status pembayaran (draft/hutang/cicilan/lunas) TIDAK
                    // dipilih manual lagi di sini — sudah dihitung otomatis
                    // sejak nota dibuat (lihat Pembelian::simpan()) dari
                    // nominal yang dibayar vs grand_total, dan tidak berubah
                    // lagi di titik validasi ini (murni informasional).
                    Placeholder::make('jenis_preview')
                        ->label('Jenis Pembayaran (dari form Tambah Pembelian)')
                        ->content(function () use ($record) {
                            $label = Pembelian::labelJenisPembayaran()[$record->jenis_pembayaran] ?? $record->jenis_pembayaran;
                            $keterangan = match ($record->jenis_pembayaran) {
                                Pembelian::JENIS_NORMAL => 'Barang & Hutang Usaha diakui PENUH sekarang. Kalau ada pembayaran bersamaan, langsung diposting sebagai pelunasan instan.',
                                Pembelian::JENIS_BAYAR_DIMUKA => 'Hanya DP yang dicatat sekarang (Uang Muka). Barang diakui nanti lewat menu Kedatangan Barang.',
                                Pembelian::JENIS_DP => 'DP tahap 1 dicatat sekarang (Uang Muka). Boleh ditambah cicilan lagi lewat menu Kedatangan Barang -> Tambah DP. Barang & pelunasan sisa diakui bersamaan saat barang datang.',
                                default => 'Jenis pembayaran tidak dikenali, cek data.',
                            };

                            return new HtmlString(
                                "<span class='font-bold'>{$label}</span><br><span class='text-xs text-gray-400'>{$keterangan}</span>"
                            );
                        }),

                    Placeholder::make('status_preview')
                        ->label('Status Pembelian (otomatis, tidak berubah saat validasi)')
                        ->content(fn () => new HtmlString(
                            "<span class='font-bold'>".e(Pembelian::labelStatus()[$record->status] ?? $record->status)."</span>"
                        )),
                ])
                ->action(function (Pembelian $record) {
                    $validatorId = filament()->auth()->id();

                    try {
                        DB::transaction(function () use ($record, $validatorId) {
                            $record->update([
                                'validated_by' => $validatorId,
                                'tanggal_validasi' => now(),
                            ]);

                            app(JurnalPembelianTriplekService::class)
                                ->buatJurnalPembelian($record, $validatorId);
                        });
                    } catch (Throwable $e) {
                        Log::error('[ViewPembelians::validasi_pembelian] Gagal memvalidasi pembelian', [
                            'pembelian_id' => $record->id,
                            'nomor_nota' => $record->nomor_nota ?? null,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        Notification::make()
                            ->title('Gagal Validasi Pembelian')
                            ->body('Terjadi kesalahan: '.$e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Pembelian Berhasil Divalidasi & Jurnal Tercatat')
                        ->success()
                        ->send();
                }),

            Action::make('batal_validasi')
                ->label('Batal Validasi')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn(Pembelian $record) => !empty($record->validated_by) && filament()->auth()->user()->hasRole('super_admin'))
                ->action(function (Pembelian $record) {
                    $userId = filament()->auth()->id();
                    $pesanNotif = 'Validasi telah dibatalkan.';

                    // CATATAN: logika batal_validasi ini masih yang LAMA (belum
                    // disesuaikan dengan alur Buku Kitab baru) — sesuai
                    // kesepakatan sebelumnya, ini diabaikan dulu untuk saat ini.
                    DB::transaction(function () use ($record, $userId, &$pesanNotif) {
                        $headersAsli = \App\Models\JurnalPembantuHeader::where('no_dokumen', $record->nomor_nota)
                            ->where('adalah_jurnal_balik', false)
                            ->where('modul_asal', 'pembelian_barang')
                            ->get();

                        $isMasihDraft = $headersAsli->contains(function ($header) {
                            return $header->status === \App\Models\JurnalPembantuHeader::STATUS_DRAFT;
                        });

                        if ($isMasihDraft) {
                            $nomorAsli  = (int) $headersAsli->first()?->jurnal;
                            $nomorFinal = $nomorAsli;

                            if ($nomorAsli > 0 && \App\Models\JurnalUmum::where('jurnal', $nomorAsli)->exists()) {
                                $nomorFinal = max(
                                    (int) (\App\Models\JurnalUmum::max('jurnal') ?? 0),
                                    (int) (\App\Models\JurnalPembantuHeader::max('jurnal') ?? 0)
                                ) + 1;

                                \App\Models\JurnalPembantuHeader::where('no_dokumen', $record->nomor_nota)
                                    ->where('adalah_jurnal_balik', false)
                                    ->where('modul_asal', 'pembelian_barang')
                                    ->update(['jurnal' => $nomorFinal]);
                            }

                            foreach ($headersAsli as $header) {
                                $itemsAktif = $header->items()->where('status', true)->get();
                                $itemsPerBarang = $itemsAktif->groupBy('id_barang');

                                foreach ($itemsPerBarang as $idBarang => $items) {
                                    $idBarangFinal = $idBarang !== '' ? $idBarang : null;

                                    $totalBanyak = (float) $items->sum('banyak');
                                    $totalM3 = (float) $items->sum('m3');
                                    $totalNilaiGrup = (float) $items->sum('jumlah');

                                    $firstItem = $items->first();
                                    $itemHitKbk = $firstItem?->hit_kbk ?? 'b';
                                    $hitKbk = $itemHitKbk;

                                    $prefix = substr($header->no_akun, 0, 3);
                                    $isCashOrPayment = in_array($prefix, ['110', '111', '112', '113', '114', '210', '220', '230']);
                                    if ($isCashOrPayment) {
                                        $hitKbk = 'b';
                                    }

                                    $m3 = $totalM3 > 0 ? $totalM3 : null;
                                    $banyak = $totalBanyak > 0 ? $totalBanyak : null;

                                    if ($hitKbk === 'm') {
                                        $hargaRata = $totalM3 > 0 ? ($totalNilaiGrup / $totalM3) : $totalNilaiGrup;
                                    } else {
                                        if (!$banyak && !$isCashOrPayment) $banyak = 1;
                                        $hargaRata = $totalBanyak > 0 ? ($totalNilaiGrup / $totalBanyak) : $totalNilaiGrup;
                                    }

                                    \App\Models\JurnalUmum::create([
                                        'tgl'        => now()->format('Y-m-d'),
                                        'jurnal'     => $nomorFinal,
                                        'no_akun'    => $header->no_akun,
                                        'nama_akun'  => $header->nama_akun,
                                        'nama'       => $record->supplier_name ?? $header->no_dokumen,
                                        'keterangan' => $header->keterangan . ' (Otomatis Terposting karena Pembatalan)',
                                        'id_barang'  => $idBarangFinal,
                                        'banyak'     => $banyak !== null ? round($banyak, 4) : null,
                                        'm3'         => $m3 !== null ? round($m3, 4) : null,
                                        'harga'      => round($hargaRata, 2),
                                        'hit_kbk'    => $hitKbk,
                                        'map'        => strtolower($header->map),
                                    ]);
                                }
                            }

                            $infoNomor  = $nomorFinal !== $nomorAsli ? " (Nomor Jurnal disesuaikan menjadi No. {$nomorFinal} karena No. {$nomorAsli} sudah terpakai)" : "";
                            $pesanNotif = "Jurnal Asli otomatis di-posting ke Jurnal Umum{$infoNomor}, dan ";
                        } else {
                            $pesanNotif = '';
                        }

                        app(JurnalBalikService::class)
                            ->buatJurnalBalikDariNota($record->nomor_nota, $userId);

                        $pesanNotif .= 'Jurnal Balik Baru berhasil diterbitkan di Jurnal Pembantu.';

                        $record->update([
                            'validated_by' => null,
                            'status'       => Pembelian::STATUS_DRAFT,
                        ]);
                    });

                    Notification::make()
                        ->title('Batal Validasi Berhasil')
                        ->body($pesanNotif)
                        ->warning()
                        ->send();
                }),

            EditAction::make(),
        ];
    }
}