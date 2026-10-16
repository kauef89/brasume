<?php
namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrderTable;
use App\Models\Order;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OrderResource extends Resource {
    protected static ?string $model = Order::class;
    
    // O ícone do carrinho e o novo grupo no menu!
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string | \UnitEnum | null $navigationGroup = 'Vendas & Atendimento';
    
    protected static ?string $modelLabel = 'Pedido';
    protected static ?string $pluralModelLabel = 'Pedidos';

    public static function form(Schema $schema): Schema { return OrderForm::configure($schema); }
    public static function table(Table $table): Table { return OrderTable::configure($table); }
    
    public static function getPages(): array {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}