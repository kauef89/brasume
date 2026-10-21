<?php

namespace App\Filament\Resources\Supplies;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\Supplies\Pages;
use App\Filament\Resources\Supplies\Schemas\SupplyForm;
use App\Filament\Resources\Supplies\Tables\SuppliesTable;
use App\Filament\Resources\Products\RelationManagers\CostHistoriesRelationManager;
use App\Filament\Resources\Products\RelationManagers\InventoryMovementsRelationManager;
use App\Models\Supply;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SupplyResource extends Resource
{
    use HasGlobalSearch;

    protected static ?string $model = Supply::class;
    protected static ?string $slug = 'material-consumo';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';
    protected static string | \UnitEnum | null $navigationGroup = 'Estoque & Compras';
    protected static ?string $modelLabel = 'Suprimento';
    protected static ?string $pluralModelLabel = 'Suprimentos';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
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
            CostHistoriesRelationManager::class,
            InventoryMovementsRelationManager::class, // 🚀 Extrato Kardex
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
