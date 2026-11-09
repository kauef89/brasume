<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Resources\Suppliers\Pages;
use App\Filament\Resources\Suppliers\RelationManagers;
use App\Filament\Resources\Suppliers\Schemas\SupplierForm;
use App\Filament\Resources\Suppliers\Tables\SuppliersTable;
use App\Filament\Traits\HasGlobalSearch;
use App\Models\Supplier;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

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
        return 'Estoque & Compras';
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

    public static function form(Schema $schema): Schema
    {
        return SupplierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SuppliedProductsRelationManager::class, // 🚀 Painel de Insumos & Cotações
            RelationManagers\FinancialRelationManager::class,        // Extrato Financeiro
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit'   => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
