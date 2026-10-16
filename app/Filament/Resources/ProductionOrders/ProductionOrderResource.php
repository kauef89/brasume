<?php

namespace App\Filament\Resources\ProductionOrders;

use App\Filament\Resources\ProductionOrders\Pages;
use App\Filament\Resources\ProductionOrders\Tables\ProductionOrdersTable;
use App\Filament\Resources\ProductionOrders\Schemas\ProductionOrderForm;
use App\Models\ProductionOrder;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class ProductionOrderResource extends Resource
{
    protected static ?string $model = ProductionOrder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string|UnitEnum|null $navigationGroup = 'Produção & Cozinha';

    protected static ?string $modelLabel = 'Ordem de Produção';
    protected static ?string $pluralModelLabel = 'Ordens de Produção';

    public static function form(Schema $form): Schema
    {
        return ProductionOrderForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return ProductionOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductionOrders::route('/'),
            'create' => Pages\CreateProductionOrder::route('/create'),
            'edit' => Pages\EditProductionOrder::route('/{record}/edit'),
        ];
    }
}
