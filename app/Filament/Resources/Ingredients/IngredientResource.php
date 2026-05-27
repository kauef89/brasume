<?php

namespace App\Filament\Resources\Ingredients;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\Ingredients\Pages;
use App\Filament\Resources\Ingredients\Schemas\IngredientForm;
use App\Filament\Resources\Ingredients\Tables\IngredientTable;
use App\Models\Product;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class IngredientResource extends Resource
{
    use HasGlobalSearch;

    protected static ?string $model = Product::class;
    protected static ?string $slug = 'insumos';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-scale';
    protected static string | \UnitEnum | null $navigationGroup = 'Estoque & Compras';
    protected static ?string $modelLabel = 'Insumo / Ingrediente';
    protected static ?string $pluralModelLabel = 'Insumos';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('category', fn($query) => $query->where('type', 'internal'));
    }

    public static function form(Schema $schema): Schema
    {
        return IngredientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IngredientTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // 🚀 Agora os Insumos Preparados também têm Ficha Técnica!
            \App\Filament\Resources\Products\RelationManagers\TechnicalSheetsRelationManager::class,
            \App\Filament\Resources\Products\RelationManagers\CostHistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIngredients::route('/'),
            'create' => Pages\CreateIngredient::route('/create'),
            'view' => Pages\ViewIngredient::route('/{record}'),
            'edit' => Pages\EditIngredient::route('/{record}/edit'),
        ];
    }
}
