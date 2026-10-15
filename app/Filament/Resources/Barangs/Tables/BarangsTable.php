<?php

namespace App\Filament\Resources\Barangs\Tables;

use App\Models\Kategori;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BarangsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('kode_barang')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('kategori.nama_kategori')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('satuan.nama_satuan')
                    ->label('Satuan')
                    ->badge()
                    ->color('info'),

                TextColumn::make('harga_beli')
                    ->label('HPP')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('harga_jual')
                    ->label('Harga Jual')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('stok_minimum')
                    ->label('Stok Min')
                    ->badge()
                    ->color(fn(int $state) => $state > 0 ? 'warning' : 'gray'),

                // ── TAMPILAN AKUN PERSEDIAAN ────────────────────────────────
                TextColumn::make('subAnakAkun.kode_sub_anak_akun')
                    ->label('Akun Persediaan')
                    ->badge()
                    ->color('warning')
                    ->placeholder('Belum diset')
                    ->alignment('center')
                    ->wrap()
                    ->description(fn ($record) => $record->subAnakAkun?->nama_sub_anak_akun)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->orWhereHas('subAnakAkun', function (Builder $q) use ($search) {
                            $q->where('kode_sub_anak_akun', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('subAnakAkun.nama_sub_anak_akun')
                    ->label('Nama Akun Persediaan')
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->orWhereHas('subAnakAkun', function (Builder $q) use ($search) {
                            $q->where('nama_sub_anak_akun', 'like', "%{$search}%");
                        });
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── TAMPILAN AKUN PENDAPATAN ────────────────────────────────
                TextColumn::make('akunPendapatan.kode_sub_anak_akun')
                    ->label('Akun Pendapatan')
                    ->badge()
                    ->color('success')
                    ->placeholder('Belum diset')
                    ->alignment('center')
                    ->wrap()
                    ->description(fn ($record) => $record->akunPendapatan?->nama_sub_anak_akun),

                // ── TAMPILAN AKUN HPP ───────────────────────────────────────
                TextColumn::make('akunHpp.kode_sub_anak_akun')
                    ->label('Akun HPP')
                    ->badge()
                    ->color('danger')
                    ->placeholder('Belum diset')
                    ->alignment('center')
                    ->wrap()
                    ->description(fn ($record) => $record->akunHpp?->nama_sub_anak_akun),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([
                // ── FILTER KATEGORI BERJENJANG ──────────────────────────────
                // Pilih kategori induk (mis. "Veneer") -> tampil SEMUA barang di
                // kategori itu + semua anaknya (Veneer Basah/Kering/Jadi).
                // Pilih kategori anak -> tampil spesifik anak itu saja.
                SelectFilter::make('id_kategori')
                    ->label('Kategori')
                    ->options(function (): array {
                        $options = [];

                        $parents = Kategori::whereNull('parent_id')
                            ->orderBy('nama_kategori')
                            ->with(['children' => fn ($q) => $q->orderBy('nama_kategori')])
                            ->get();

                        foreach ($parents as $parent) {
                            $options[$parent->id] = $parent->nama_kategori;

                            foreach ($parent->children as $child) {
                                $options[$child->id] = '— '.$child->nama_kategori;
                            }
                        }

                        return $options;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $selected = $data['value'] ?? null;

                        if (blank($selected)) {
                            return $query;
                        }

                        $kategori = Kategori::find($selected);

                        if (! $kategori) {
                            return $query;
                        }

                        // Kategori induk (parent_id null) -> gabung id-nya sendiri + semua id anak
                        $ids = $kategori->parent_id === null
                            ? $kategori->children()->pluck('id')->push($kategori->id)
                            : collect([$kategori->id]);

                        return $query->whereIn('id_kategori', $ids);
                    }),
            ])
            ->deferFilters(false) // langsung apply begitu dipilih, tidak perlu klik "Apply" lagi
            ->recordActions([
                // ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nama_barang');
    }
}