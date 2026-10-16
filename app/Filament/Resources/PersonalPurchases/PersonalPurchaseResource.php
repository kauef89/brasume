<?php

namespace App\Filament\Resources\PersonalPurchases;

use App\Filament\Resources\PersonalPurchases\Pages;
use App\Models\PersonalPurchase;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;

// IMPORTAMOS AS CLASSES MODULARES
use App\Filament\Resources\PersonalPurchases\Schemas\PersonalPurchaseForm;
use App\Filament\Resources\PersonalPurchases\Tables\PersonalPurchasesTable;

class PersonalPurchaseResource extends Resource
{
    protected static ?string $model = PersonalPurchase::class;
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | \UnitEnum | null $navigationGroup = 'Financeiro';
    protected static ?string $modelLabel = 'Item de Despesa';
    protected static ?string $pluralModelLabel = 'Despesas de Notas';

    public static function form(Schema $schema): Schema
    {
        // 🚀 Delega para a classe modular
        return PersonalPurchaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        // 🚀 Delega para a classe modular (Onde o nosso botão Desfazer vive!)
        return PersonalPurchasesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false; // Desativa o botão de criar novo
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonalPurchases::route('/'),
        ];
    }
}