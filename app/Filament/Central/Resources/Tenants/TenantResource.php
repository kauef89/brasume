<?php

namespace App\Filament\Central\Resources\Tenants;

use App\Filament\Central\Resources\Tenants\Pages;
use App\Filament\Central\Resources\Tenants\Forms\TenantForm;
use App\Filament\Central\Resources\Tenants\Tables\TenantTable;
use App\Models\Tenant;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';
    
    protected static ?string $navigationLabel = 'Clientes';
    
    protected static ?string $modelLabel = 'Cliente';

    public static function form(Schema $schema): Schema
    {
        // Passamos a nova variável $schema para a classe isolada
        return TenantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        // Delegamos a construção da tabela para a classe isolada
        return TenantTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}