<?php

namespace App\Filament\Resources\Products;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\Products\Pages;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductTable;
use App\Models\Product;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

class ProductResource extends Resource
{
    use HasGlobalSearch;

    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = "heroicon-o-shopping-bag";
    protected static string|\UnitEnum|null $navigationGroup = "Cardápio";

    protected static ?string $modelLabel = "Produto";
    protected static ?string $pluralModelLabel = "Produtos";

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas(
            "category",
            fn($query) => $query->where("type", "external"),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Products\RelationManagers\CostHistoriesRelationManager::class,
            \App\Filament\Resources\Products\RelationManagers\TechnicalSheetsRelationManager::class,
            \App\Filament\Resources\Products\RelationManagers\ProductModifiersRelationManager::class,
            \App\Filament\Resources\Products\RelationManagers\SimulatorRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            "index" => Pages\ListProducts::route("/"),
            "create" => Pages\CreateProduct::route("/create"),
            "view" => Pages\ViewProduct::route("/{record}"),
            "edit" => Pages\EditProduct::route("/{record}/edit"),
        ];
    }
}
