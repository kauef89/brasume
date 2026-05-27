<?php

namespace App\Filament\Resources\FixedAccounts;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\FixedAccounts\Pages;
use App\Filament\Resources\FixedAccounts\Schemas\FixedAccountForm;
use App\Filament\Resources\FixedAccounts\Tables\FixedAccountsTable;
use App\Models\FixedAccount;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class FixedAccountResource extends Resource
{
    use HasGlobalSearch;
    protected static ?string $model = FixedAccount::class;
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static string | \UnitEnum | null $navigationGroup = 'Financeiro';
    protected static ?string $modelLabel = 'Conta Fixa';
    protected static ?string $pluralModelLabel = 'Contas Fixas';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        // Delega o esquema visual para a classe de Form
        return FixedAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        // Delega as colunas e filtros para a classe de Table
        return FixedAccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFixedAccounts::route('/'),
            'create' => Pages\CreateFixedAccount::route('/create'),
            'edit' => Pages\EditFixedAccount::route('/{record}/edit'),
        ];
    }
}