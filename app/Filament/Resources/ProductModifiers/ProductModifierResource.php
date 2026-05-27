<?php

namespace App\Filament\Resources\ProductModifiers; 

use App\Filament\Resources\ProductModifiers\Pages;
use App\Filament\Resources\ProductModifiers\RelationManagers;
use App\Models\ProductModifier; 
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

// A MÁGICA ESTÁ AQUI: Importamos as nossas classes isoladas!
use App\Filament\Resources\ProductModifiers\Schemas\ProductModifierForm;
use App\Filament\Resources\ProductModifiers\Tables\ProductModifierTable;

class ProductModifierResource extends Resource
{
    protected static ?string $model = ProductModifier::class; 
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static string | \UnitEnum | null $navigationGroup = 'Cardápio';
    protected static ?string $modelLabel = 'Modificador';
    protected static ?string $pluralModelLabel = 'Modificadores';

    // O RESOURCE AGORA APENAS DELEGA O TRABALHO PARA O ARQUIVO EXTERNO
    public static function form(Schema $schema): Schema
    {
        return ProductModifierForm::configure($schema);
    }

    // O MESMO ACONTECE PARA A TABELA
    public static function table(Table $table): Table
    {
        return ProductModifierTable::configure($table);
    }

    public static function getRelations(): array
    {
        // Mantemos o RelationManager aqui para as opções aparecerem embaixo
        return [RelationManagers\OptionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModifierGroups::route('/'),
            'create' => Pages\CreateModifierGroup::route('/create'),
            'edit' => Pages\EditModifierGroup::route('/{record}/edit'),
        ];
    }
}