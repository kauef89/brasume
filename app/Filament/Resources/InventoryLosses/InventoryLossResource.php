<?php

namespace App\Filament\Resources\InventoryLosses;

use App\Filament\Resources\InventoryLosses\Pages;
use App\Filament\Resources\InventoryLosses\Schemas\InventoryLossForm;
use App\Filament\Resources\InventoryLosses\Tables\InventoryLossesTable;
use App\Models\InventoryLoss;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class InventoryLossResource extends Resource
{
    protected static ?string $model = InventoryLoss::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-trash';
    protected static string | \UnitEnum | null $navigationGroup = 'Estoque & Compras';
    protected static ?string $modelLabel = 'Perda / Descarte';
    protected static ?string $pluralModelLabel = 'Perdas & Descartes';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return InventoryLossForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryLossesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListInventoryLosses::route('/'),
            'create' => Pages\CreateInventoryLoss::route('/create'),
        ];
    }
}
