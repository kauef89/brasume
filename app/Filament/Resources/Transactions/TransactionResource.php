<?php

namespace App\Filament\Resources\Transactions; 

use App\Filament\Resources\Transactions\Pages;
use App\Models\Transaction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

// ======= AS LINHAS MÁGICAS QUE FALTAVAM =======
use App\Filament\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\Resources\Transactions\Tables\TransactionsTable; 
// ==============================================

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    public static function getNavigationIcon(): string | \BackedEnum | null
    {
        return 'heroicon-o-arrows-right-left';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financeiro';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getModelLabel(): string
    {
        return 'Transação';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Livro Caixa';
    }

    public static function form(Schema $schema): Schema
    {
        // Agora o PHP sabe onde achar o TransactionForm!
        return TransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        // Agora o PHP sabe onde achar o TransactionsTable!
        return TransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}