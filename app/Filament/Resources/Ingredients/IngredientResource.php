<?php

namespace App\Filament\Resources\Ingredients;

use App\Filament\Resources\Ingredients\Pages;
use App\Filament\Resources\Ingredients\Schemas\IngredientForm;
use App\Filament\Resources\Ingredients\Tables\IngredientsTable;
use App\Filament\Resources\Products\RelationManagers\CostHistoriesRelationManager;
use App\Filament\Resources\Products\RelationManagers\InventoryMovementsRelationManager;
use App\Filament\Resources\Products\RelationManagers\TechnicalSheetsRelationManager;
use App\Filament\Traits\HasGlobalSearch;
use App\Models\Ingredient;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class IngredientResource extends Resource
{
    use HasGlobalSearch;

    protected static ?string $model = Ingredient::class;
    protected static ?string $slug = 'insumos';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-scale';
    protected static string | \UnitEnum | null $navigationGroup = 'Estoque & Compras';
    protected static ?string $modelLabel = 'Insumo / Ingrediente';
    protected static ?string $pluralModelLabel = 'Insumos';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->addSelect([
                'latest_unit_cost' => DB::table('product_cost_histories')
                    ->select('unit_cost')
                    ->whereColumn('product_cost_histories.product_id', 'products.id')
                    ->orderByDesc('id')
                    ->limit(1),
                'previous_unit_cost' => DB::table('product_cost_histories')
                    ->select('unit_cost')
                    ->whereColumn('product_cost_histories.product_id', 'products.id')
                    ->orderByDesc('id')
                    ->offset(1)
                    ->limit(1),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return IngredientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IngredientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TechnicalSheetsRelationManager::class,
            CostHistoriesRelationManager::class,
            InventoryMovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListIngredients::route('/'),
            'create' => Pages\CreateIngredient::route('/create'),
            'view'   => Pages\ViewIngredient::route('/{record}'),
            'edit'   => Pages\EditIngredient::route('/{record}/edit'),
        ];
    }
}
