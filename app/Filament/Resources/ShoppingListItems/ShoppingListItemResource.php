<?php

namespace App\Filament\Resources\ShoppingListItems;

use App\Filament\Resources\ShoppingListItems\Schemas\ShoppingListItemForm;
use App\Filament\Resources\ShoppingListItems\Tables\ShoppingListItemsTable;
use App\Models\ShoppingListItem;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class ShoppingListItemResource extends Resource
{
    protected static ?string $model = ShoppingListItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string|UnitEnum|null $navigationGroup = 'Estoque & Compras';

    protected static ?string $modelLabel = 'Lista de Compra';
    protected static ?string $pluralModelLabel = 'Listas de Compras';

    public static function form(Schema $form): Schema
    {
        return ShoppingListItemForm::configure($form);
    }

    public static function table(Table $table): Table
    {
        return ShoppingListItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShoppingListItems::route('/'),
            'create' => Pages\CreateShoppingListItem::route('/create'),
            'edit' => Pages\EditShoppingListItem::route('/{record}/edit'),
        ];
    }
}
