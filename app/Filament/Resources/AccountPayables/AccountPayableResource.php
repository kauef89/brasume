<?php

namespace App\Filament\Resources\AccountPayables;

use App\Filament\Resources\AccountPayables\Pages;
use App\Models\AccountPayable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\AccountPayables\Schemas\AccountPayableForm;
use App\Filament\Resources\AccountPayables\Tables\AccountPayablesTable;

class AccountPayableResource extends Resource
{
    protected static ?string $model = AccountPayable::class;
    protected static string|\UnitEnum|null $navigationGroup = "Financeiro";
    protected static ?string $modelLabel = "Conta a Pagar";
    protected static ?string $pluralModelLabel = "Contas a Pagar";
    protected static ?string $slug = "contas-a-pagar";

    public static function form(Schema $schema): Schema
    {
        return AccountPayableForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountPayablesTable::build($table);
    }

    public static function getPages(): array
    {
        return [
            "index" => Pages\ListAccountPayables::route("/"),
            "create" => Pages\CreateAccountPayable::route("/create"),
            "edit" => Pages\EditAccountPayable::route("/{record}/edit"),
        ];
    }
}
