<?php

namespace App\Filament\Resources\BukuKitabs;

use App\Filament\Resources\BukuKitabs\Pages\ListBukuKitabs;
use App\Filament\Resources\BukuKitabs\Pages\CreateBukuKitab;
use App\Filament\Resources\BukuKitabs\Pages\EditBukuKitab;
use App\Filament\Resources\BukuKitabs\Schemas\BukuKitabForm;
use App\Filament\Resources\BukuKitabs\Tables\BukuKitabsTable;
use App\Models\BukuKitab;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class BukuKitabResource extends Resource
{
    protected static ?string $model = BukuKitab::class;

    protected static ?string $navigationLabel = 'Buku Kitab';
    protected static ?string $modelLabel = 'Buku Kitab';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal & Akuntansi';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return BukuKitabForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BukuKitabsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListBukuKitabs::route('/'),
            'create' => CreateBukuKitab::route('/create'),
            'edit'   => EditBukuKitab::route('/{record}/edit'),
        ];
    }
}