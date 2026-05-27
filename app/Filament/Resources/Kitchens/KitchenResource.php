<?php

namespace App\Filament\Resources\Kitchens; // <-- Agora ele mora DENTRO da pasta Kitchens!

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\Kitchens\Pages;
use App\Filament\Resources\Kitchens\Schemas\KitchenForm;
use App\Filament\Resources\Kitchens\Tables\KitchenTable;
use App\Models\Kitchen;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class KitchenResource extends Resource
{
    use HasGlobalSearch;
    protected static ?string $model = Kitchen::class;
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-fire';
    protected static string | \UnitEnum | null $navigationGroup = 'Logística';
    protected static ?string $modelLabel = 'Cozinha';
    protected static ?string $pluralModelLabel = 'Cozinhas';

    public static function form(Schema $schema): Schema
    {
        return KitchenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KitchenTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKitchens::route('/'),
            'create' => Pages\CreateKitchen::route('/create'),
            'edit' => Pages\EditKitchen::route('/{record}/edit'),
        ];
    }
}