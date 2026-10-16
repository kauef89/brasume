<?php

namespace App\Filament\Resources\RecurringExpenses;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\RecurringExpenses\Pages;
use App\Filament\Resources\RecurringExpenses\Schemas\RecurringExpenseForm;
use App\Filament\Resources\RecurringExpenses\Tables\RecurringExpensesTable;
use App\Filament\Resources\RecurringExpenses\RelationManagers\AccountPayablesRelationManager;
use App\Models\RecurringExpense;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class RecurringExpenseResource extends Resource
{
    use HasGlobalSearch;
    protected static ?string $model = RecurringExpense::class;
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static string | \UnitEnum | null $navigationGroup = 'Financeiro';
    protected static ?string $modelLabel = 'Despesa Recorrente';
    protected static ?string $pluralModelLabel = 'Despesas Recorrentes';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        // Delega o esquema visual para a classe de Form
        return RecurringExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        // Delega as colunas e filtros para a classe de Table
        return RecurringExpensesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AccountPayablesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecurringExpenses::route('/'),
            'create' => Pages\CreateRecurringExpense::route('/create'),
            'view' => Pages\ViewRecurringExpense::route('/{record}'), // 🚀 A NOVA TELA DE VISUALIZAÇÃO
            'edit' => Pages\EditRecurringExpense::route('/{record}/edit'),
        ];
    }
}
