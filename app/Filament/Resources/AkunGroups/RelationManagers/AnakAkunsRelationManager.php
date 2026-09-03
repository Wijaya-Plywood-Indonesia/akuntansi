<?php

namespace App\Filament\Resources\AkunGroups\RelationManagers;

use App\Models\AnakAkun;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Tables;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class AnakAkunsRelationManager extends RelationManager
{
    public function isReadOnly(): bool
    {
        return false;
    }

    protected static string $relationship = 'anakAkuns';

    protected static ?string $title = 'Daftar Akun';

    /*
    |--------------------------------------------------------------------------
    | Leaf Only Enforcement
    |--------------------------------------------------------------------------
    */

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->isLeaf();
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->recordTitleAttribute('nama_anak_akun')
            ->columns([
                TextColumn::make('kode_anak_akun')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama_anak_akun')
                    ->label('Nama Akun')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('indukAkun.nama_induk_akun')
                    ->label('Induk')
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Daftarkan Akun')
                    ->preloadRecordSelect()
                    ->multiple()
                    ->recordTitle(
                        fn (AnakAkun $record) =>
                        "{$record->kode_anak_akun} - {$record->nama_anak_akun}"
                    )
                    ->recordSelectSearchColumns([
                        'kode_anak_akun',
                        'nama_anak_akun',
                    ])
                    ->recordSelectOptionsQuery(function ($query) {
                        $query->where('status', 'aktif');

                        // Sejak grup arus kas dipisah TOTAL dari grup struktural
                        // (tidak ada lagi grup "hybrid" yang merangkap fungsi
                        // Laba Rugi/Neraca sekaligus Arus Kas), aturannya jadi
                        // sederhana — cukup satu kondisi:
                        $iniGrupArusKas = filled($this->getOwnerRecord()->kategori_arus_kas);

                        if ($iniGrupArusKas) {
                            // Grup bantu ARUS KAS (mis. "[Arus Kas] Penjualan").
                            // Akun BOLEH sudah terdaftar di grup struktural
                            // manapun — itu memang dirancang rangkap (1 akun
                            // = 1 rumah struktural + boleh beberapa kartu
                            // arus kas). Yang tetap dikunci: tidak boleh
                            // dobel di 2 grup arus kas berbeda sekaligus,
                            // supaya kategori kas-nya tidak ambigu.
                            return $query->whereDoesntHave(
                                'akunGroups',
                                fn ($q) => $q->whereNotNull('akun_groups.kategori_arus_kas')
                            );
                        }

                        // Grup STRUKTURAL (Aktiva Lancar, Pasiva, Pendapatan
                        // Penjualan, Beban, HPP, dst). Lock GLOBAL PENUH:
                        // akun yang sudah terdaftar di grup struktural
                        // manapun tidak akan muncul lagi di sini. Ini
                        // proteksi utama supaya admin tidak salah
                        // mendaftarkan akun kepala yang sama ke >1 grup
                        // struktural (mis. akun Aset ikut kepencet masuk
                        // grup Pendapatan).
                        return $query->whereDoesntHave('akunGroups');
                    }),
            ])
            ->actions([
                DetachAction::make(),
            ])
            ->bulkActions([
                DetachBulkAction::make(),
            ]);
    }
}