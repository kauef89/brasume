<?php

namespace App\Filament\Resources\BankAccounts;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\BankAccounts\Pages;
use App\Models\BankAccount;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

// Importando as nossas classes desacopladas
use App\Filament\Resources\BankAccounts\Schemas\BankAccountForm;
use App\Filament\Resources\BankAccounts\Tables\BankAccountsTable;

class BankAccountResource extends Resource
{
    use HasGlobalSearch;
    protected static ?string $model = BankAccount::class;
    
    // Substituindo as propriedades pelas funções Getter oficias
    public static function getNavigationIcon(): string | \BackedEnum | null
    {
        return 'heroicon-o-building-library';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financeiro';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }
    
    public static function getModelLabel(): string
    {
        return 'Conta';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Contas';
    }

    public static function form(Schema $schema): Schema
    {
        return BankAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}