<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\Suppliers\Pages;
use App\Filament\Resources\Suppliers\RelationManagers;
use App\Filament\Resources\Suppliers\Schemas\SupplierForm;
use App\Filament\Resources\Suppliers\Tables\SuppliersTable;
use App\Models\Supplier;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class SupplierResource extends Resource
{
    use HasGlobalSearch;
    protected static ?string $model = Supplier::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Estoque & Compras';

    public static function getNavigationIcon(): string | \BackedEnum | null
    {
        return 'heroicon-o-truck';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financeiro';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function getModelLabel(): string
    {
        return 'Fornecedor';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Fornecedores';
    }

    // Delega o formulário para a classe separada!
    public static function form(Schema $schema): Schema
    {
        return SupplierForm::configure($schema);
    }

    // Delega a tabela para a classe separada!
    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
    }

    // Regista a nossa aba de Histórico
    public static function getRelations(): array
    {
        return [
            RelationManagers\FinancialRelationManager::class, // <-- A Tabela Bonita Volta aqui!
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }

}
