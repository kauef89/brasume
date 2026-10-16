<?php

namespace App\Filament\Resources\StorageLocations;

use App\Models\StorageLocation;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;

// Suas classes fatiadas
use App\Filament\Resources\StorageLocations\Schemas\StorageLocationForm;
use App\Filament\Resources\StorageLocations\Tables\StorageLocationsTable;
use App\Filament\Resources\StorageLocations\Pages;

class StorageLocationResource extends Resource
{
    protected static ?string $model = StorageLocation::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Locais de Armazenamento';
    protected static ?string $modelLabel = 'Local de Armazenamento';

    // Tipagem estrita
    protected static string | \UnitEnum | null $navigationGroup = 'Estoque & Compras';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(StorageLocationForm::schema());
    }

    public static function table(Table $table): Table
    {
        return StorageLocationsTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStorageLocations::route('/'),
            'create' => Pages\CreateStorageLocation::route('/create'),
            'edit' => Pages\EditStorageLocation::route('/{record}/edit'),
        ];
    }
}
