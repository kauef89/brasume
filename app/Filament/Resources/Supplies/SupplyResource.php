<?php

namespace App\Filament\Resources\Supplies;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\Supplies\Pages;
use App\Filament\Resources\Supplies\Schemas\SupplyForm;
use App\Filament\Resources\Supplies\Tables\SuppliesTable;
use App\Models\Product;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SupplyResource extends Resource
{
    use HasGlobalSearch;

    protected static ?string $model = Product::class;
    protected static ?string $slug = 'material-consumo';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';
    protected static string | \UnitEnum | null $navigationGroup = 'Estoque & Compras';
    protected static ?string $modelLabel = 'Material de Consumo';
    protected static ?string $pluralModelLabel = 'Mat. de Consumo';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('category', fn($query) => $query->where('type', 'supply'));
    }

    public static function form(Schema $schema): Schema
    {
        return SupplyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuppliesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Products\RelationManagers\CostHistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplies::route('/'),
            'create' => Pages\CreateSupply::route('/create'),
            'view' => Pages\ViewSupply::route('/{record}'),
            'edit' => Pages\EditSupply::route('/{record}/edit'),
        ];
    }
}
