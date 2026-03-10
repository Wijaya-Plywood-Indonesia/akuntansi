<?php

namespace App\Filament\Resources\KelompokMappingAkuns;

use App\Filament\Resources\KelompokMappingAkuns\Pages\CreateKelompokMappingAkun;
use App\Filament\Resources\KelompokMappingAkuns\Pages\EditKelompokMappingAkun;
use App\Filament\Resources\KelompokMappingAkuns\Pages\ListKelompokMappingAkuns;
use App\Filament\Resources\KelompokMappingAkuns\Pages\ViewKelompokMappingAkun;
use App\Filament\Resources\KelompokMappingAkuns\RelationManagers\MappingAkunRelationManager;
use App\Filament\Resources\KelompokMappingAkuns\Schemas\KelompokMappingAkunForm;
use App\Filament\Resources\KelompokMappingAkuns\Schemas\KelompokMappingAkunInfolist;
use App\Filament\Resources\KelompokMappingAkuns\Tables\KelompokMappingAkunsTable;
use App\Models\KelompokMappingAkun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KelompokMappingAkunResource extends Resource
{
    protected static ?string $model = KelompokMappingAkun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return KelompokMappingAkunForm::configure($schema);
    }
    public static function getRelations(): array
    {
        return [
            MappingAkunRelationManager::class,
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return KelompokMappingAkunInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KelompokMappingAkunsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKelompokMappingAkuns::route('/'),
            'create' => CreateKelompokMappingAkun::route('/create'),
            'view' => ViewKelompokMappingAkun::route('/{record}'),
            'edit' => EditKelompokMappingAkun::route('/{record}/edit'),
        ];
    }
}
