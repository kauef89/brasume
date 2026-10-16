<?php

namespace App\Filament\Resources\BankAccounts;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\BankAccounts\Pages;
use App\Models\BankAccount;
use Filament\Resources\Resource;
use Filament\Schemas\Schema; // 🚀 REVERTIDO: Voltamos para o Schema oficial do seu sistema!
use Filament\Tables\Table;

// Importando as nossas classes desacopladas
use App\Filament\Resources\BankAccounts\Schemas\BankAccountForm;
use App\Filament\Resources\BankAccounts\Tables\BankAccountsTable;
use App\Filament\Resources\BankAccounts\RelationManagers\AccountReceivablesRelationManager; // 🚀 O nosso Motor de Recebimentos continua aqui!

class BankAccountResource extends Resource
{
    use HasGlobalSearch;

    protected static ?string $model = BankAccount::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

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

    // 🚀 REVERTIDO: A assinatura volta a usar o Schema corretamente!
    public static function form(Schema $schema): Schema
    {
        return BankAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankAccountsTable::configure($table);
    }

    // 🚀 INJEÇÃO DO NOVO MOTOR MANTIDA
    public static function getRelations(): array
    {
        return [
            AccountReceivablesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'view' => Pages\ViewBankAccount::route('/{record}'), // 🚀 NOVA TELA!
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}
