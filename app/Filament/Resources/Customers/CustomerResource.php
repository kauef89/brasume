<?php
namespace App\Filament\Resources\Customers;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\Customers\Pages;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Resources\Customers\Tables\CustomerTable;
use App\Models\Customer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CustomerResource extends Resource {
    use HasGlobalSearch;
    protected static ?string $model = Customer::class;
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'CRM & Marketing'; // Um novo grupo para Clientes!
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';

    public static function form(Schema $schema): Schema { return CustomerForm::configure($schema); }
    public static function table(Table $table): Table { return CustomerTable::configure($table); }
    
    // Aqui no futuro adicionaremos os RelationManagers de Pedidos e Fidelidade!
    public static function getRelations(): array 
    {
        return [
            \App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager::class,

    ];
    }
    
    public static function getPages(): array {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'), // Atenção aos nomes!
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}