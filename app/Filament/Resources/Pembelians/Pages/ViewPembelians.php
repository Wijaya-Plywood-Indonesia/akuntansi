<?php

namespace App\Filament\Resources\Pembelians\Pages;

use App\Filament\Resources\Pembelians\PembeliansResource;
use App\Models\Pembelian;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use App\Services\JurnalPembelianService;
use App\Services\JurnalBalikService;

class ViewPembelians extends ViewRecord
{
    protected static string $resource = PembeliansResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ✅ ACTION: VALIDASI PEMBELIAN (Dipindah ke View)
            Action::make('validasi_pembelian')
                ->label('Validasi')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()

                // ── PERBAIKAN LOGIKA: VALIDASI PEMBAYARAN KHUSUS DP & CICILAN DENGAN METODE REFLEKSI DINAMIS ──
                ->visible(function (Pembelian $record) {
                    // 1. Kondisi dasar: Pembelian belum divalidasi & status bukan Batal
                    $isEligible = empty($record->validated_by) && $record->status !== Pembelian::STATUS_BATAL;

                    if (!$isEligible) {
                        return false;
                    }

                    // 2. Tentukan nominal total tagihan pembelian
                    $totalTagihan = (float) ($record->grand_total ?? $record->total_harga ?? $record->total ?? 0);

                    // 3. DETEKSI OTOMATIS AKUMULASI PEMBAYARAN YANG SUDAH DIBAYARKAN (DP / Cicilan)
                    $totalSudahDibayar = 0.0;

                    // Langkah A: Scan atribut model fisik yang mengandung unsur kata bayar/paid/dibayar
                    foreach ($record->getAttributes() as $key => $val) {
                        if (in_array($key, ['total_harga', 'grand_total', 'total', 'total_tagihan'])) {
                            continue;
                        }
                        if (str_contains($key, 'bayar') || str_contains($key, 'dibayar') || str_contains($key, 'paid')) {
                            $totalSudahDibayar = (float) $val;
                            if ($totalSudahDibayar > 0) {
                                break;
                            }
                        }
                    }

                    // Langkah B: REFLEKSI DINAMIS - Cari semua method relasi pembayaran & baca skema kolomnya dari DB secara cerdas
                    $detectedRelations = [];
                    foreach (get_class_methods($record) as $method) {
                        // Lewati metode bawaan Eloquent
                        if (method_exists(\Illuminate\Database\Eloquent\Model::class, $method)) {
                            continue;
                        }

                        $methodLower = strtolower($method);
                        if (
                            str_contains($methodLower, 'pembayaran') ||
                            str_contains($methodLower, 'metode') ||
                            str_contains($methodLower, 'payment') ||
                            str_contains($methodLower, 'history') ||
                            str_contains($methodLower, 'riwayat')
                        ) {
                            try {
                                $relation = $record->$method();
                                if ($relation instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                                    $detectedRelations[] = $method;
                                }
                            } catch (\Throwable $e) {
                                // Abaikan jika method membutuhkan parameter tambahan
                            }
                        }
                    }

                    // Tambahkan list fallback statis untuk keamanan tambahan
                    $fallbackRelations = ['pembayarans', 'pembayaran', 'metodePembayarans', 'metodePembayaran', 'riwayatPembayarans', 'riwayatPembayaran', 'payments', 'payment', 'pembelianPembayarans'];
                    $allRelations = array_unique(array_merge($detectedRelations, $fallbackRelations));

                    if ($totalSudahDibayar <= 0) {
                        foreach ($allRelations as $rel) {
                            if (method_exists($record, $rel)) {
                                try {
                                    $relation = $record->$rel();
                                    $relatedModel = $relation->getRelated();
                                    $table = $relatedModel->getTable();

                                    // Tarik skema kolom tabel secara real-time dari database
                                    $schemaColumns = \Illuminate\Support\Facades\Schema::getColumnListing($table);

                                    // Tentukan nama kolom jumlah pembayaran yang paling cocok di DB Anda
                                    $matchedColumn = null;
                                    $priorityColumns = ['nominal_terbayar', 'nominal', 'jumlah', 'nominal_bayar', 'bayar', 'amount_paid', 'amount', 'total'];

                                    foreach ($priorityColumns as $pCol) {
                                        if (in_array($pCol, $schemaColumns)) {
                                            $matchedColumn = $pCol;
                                            break;
                                        }
                                    }

                                    if (!$matchedColumn) {
                                        foreach ($schemaColumns as $sCol) {
                                            $sColLower = strtolower($sCol);
                                            if (
                                                str_contains($sColLower, 'nominal') ||
                                                str_contains($sColLower, 'jumlah') ||
                                                str_contains($sColLower, 'bayar') ||
                                                str_contains($sColLower, 'amount') ||
                                                str_contains($sColLower, 'value')
                                            ) {
                                                $matchedColumn = $sCol;
                                                break;
                                            }
                                        }
                                    }

                                    if ($matchedColumn) {
                                        $sum = (float) $record->$rel()->sum($matchedColumn);
                                        if ($sum > 0) {
                                            $totalSudahDibayar = $sum;
                                            break; // Hentikan pencarian jika sudah berhasil mendapatkan akumulasi saldo
                                        }
                                    }
                                } catch (\Throwable $e) {
                                    // Abaikan error skema
                                }
                            }
                        }
                    }

                    // 4. DETEKSI APAKAH METODE PEMBAYARAN ADALAH CICILAN ATAU DP
                    $isCicilanAtauDP = false;

                    // Langkah C: Scan atribut model fisik untuk kolom metode/jenis pembayaran
                    foreach ($record->getAttributes() as $key => $val) {
                        if (str_contains($key, 'metode') || str_contains($key, 'tipe') || str_contains($key, 'jenis') || str_contains($key, 'status')) {
                            $valLower = strtolower((string)$val);
                            if (str_contains($valLower, 'cicil') || str_contains($valLower, 'dp') || str_contains($valLower, 'down')) {
                                $isCicilanAtauDP = true;
                                break;
                            }
                        }
                    }

                    // Langkah D: Jika belum ketemu di tabel utama, cari di relasi pembayarannya secara dinamis
                    if (!$isCicilanAtauDP) {
                        foreach ($allRelations as $rel) {
                            if (method_exists($record, $rel)) {
                                try {
                                    $pembayarans = $record->$rel()->get();
                                    foreach ($pembayarans as $p) {
                                        foreach ($p->getAttributes() as $k => $v) {
                                            if (str_contains($k, 'metode') || str_contains($k, 'tipe') || str_contains($k, 'jenis')) {
                                                $vLower = strtolower((string)$v);
                                                if (str_contains($vLower, 'cicil') || str_contains($vLower, 'dp') || str_contains($vLower, 'down')) {
                                                    $isCicilanAtauDP = true;
                                                    break 3; // keluar dari semua loop pencarian
                                                }
                                            }
                                        }
                                    }
                                } catch (\Throwable $e) {
                                    // Abaikan jika relasi bermasalah
                                }
                            }
                        }
                    }

                    // 5. LOGIKA VALIDASI UTAMA:
                    // Jika metode pembayarannya terdeteksi adalah 'Cicilan' atau 'DP'
                    if ($isCicilanAtauDP) {
                        // Tombol validasi HANYA MUNCUL jika total terbayar sudah lunas (setara/lebih besar dari total tagihan)
                        return abs($totalSudahDibayar - $totalTagihan) < 1;
                    }

                    // Jika tipe pembayarannya tunai (cash) langsung, atau kredit/tempo murni tanpa cicilan/DP berjalan,
                    // kita izinkan validasi agar utang/kas tercatat secara instan ke dalam sistem akuntansi
                    return true;
                })
                // ──────────────────────────────────────────────────────────────────────

                ->disabled(fn(Pembelian $record) => $record->created_by === filament()->auth()->id() && !filament()->auth()->user()->hasRole('super_admin'))
                ->form([
                    TextInput::make('validator_name')
                        ->label('Petugas Validasi')
                        ->default(fn() => filament()->auth()->user()->name)
                        ->disabled()
                        ->dehydrated(false),

                    Select::make('status')
                        ->label('Update Status Pembelian')
                        ->options(Pembelian::labelStatus())
                        ->required()
                        ->disableOptionWhen(fn(string $value): bool => $value === Pembelian::STATUS_DRAFT),
                ])
                ->action(function (Pembelian $record, array $data) {
                    $validatorId = filament()->auth()->id();

                    DB::transaction(function () use ($record, $data, $validatorId) {
                        $record->update([
                            'validated_by' => $validatorId,
                            'status'       => $data['status'],
                            'tanggal_validasi' => now(),
                        ]);

                        app(JurnalPembelianService::class)
                            ->buatJurnalDariPembelian($record, $validatorId);
                    });

                    Notification::make()
                        ->title('Pembelian Berhasil Divalidasi & Jurnal Tercatat')
                        ->success()
                        ->send();
                }),

            // ❌ ACTION: BATAL VALIDASI (Dipindah ke View)
            Action::make('batal_validasi')
                ->label('Batal Validasi')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn(Pembelian $record) => !empty($record->validated_by) && filament()->auth()->user()->hasRole('super_admin'))
                ->action(function (Pembelian $record) {
                    $userId = filament()->auth()->id();
                    $pesanNotif = 'Validasi telah dibatalkan.';

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
                                $totalBanyak = $itemsAktif->sum('banyak');
                                $totalM3 = $itemsAktif->sum('m3');
                                $totalJumlah = $itemsAktif->sum('jumlah');

                                $firstItem = $itemsAktif->first();
                                $itemHitKbk = $firstItem?->hit_kbk;

                                $hitKbk = '';
                                $prefix = substr($header->no_akun, 0, 3);
                                $isCashOrPayment = in_array($prefix, ['110', '111', '112', '113', '114', '210', '220', '230']);

                                if (!$isCashOrPayment) {
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
                                    $harga = $totalM3 > 0 ? ($totalJumlah / $totalM3) : (float) $header->total_nilai;
                                } elseif ($hitKbk === 'b') {
                                    $m3 = $totalM3 > 0 ? $totalM3 : null;
                                    $banyak = $totalBanyak > 0 ? $totalBanyak : 1;
                                    $harga = $totalBanyak > 0 ? ($totalJumlah / $totalBanyak) : (float) $header->total_nilai;
                                } else {
                                    $m3 = $totalM3 > 0 ? $totalM3 : null;
                                    $banyak = $totalBanyak > 0 ? $totalBanyak : null;
                                    $harga = (float) $header->total_nilai;
                                }

                                \App\Models\JurnalUmum::create([
                                    'tgl'        => now()->format('Y-m-d'),
                                    'jurnal'     => $nomorFinal,
                                    'no_akun'    => $header->no_akun,
                                    'nama_akun'  => $header->nama_akun,
                                    'nama'       => $record->supplier_name ?? $header->no_dokumen,
                                    'keterangan' => $header->keterangan . ' (Otomatis Terposting karena Pembatalan)',
                                    'banyak'     => $banyak !== null ? round($banyak, 4) : null,
                                    'm3'         => $m3 !== null ? round($m3, 4) : null,
                                    'harga'      => round($harga, 2),
                                    'hit_kbk'    => $hitKbk,
                                    'map'        => strtolower($header->map),
                                ]);
                            }

                            $infoNomor  = $nomorFinal !== $nomorAsli ? " (Nomor Jurnal disesuaikan menjadi No. {$nomorFinal} because No. {$nomorAsli} sudah terpakai)" : "";
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
