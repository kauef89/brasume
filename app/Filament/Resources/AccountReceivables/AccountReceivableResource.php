<?php

namespace App\Filament\Resources\AccountReceivables;

use App\Filament\Resources\AccountReceivables\Pages;
use App\Models\AccountReceivable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

use App\Filament\Resources\AccountReceivables\Schemas\AccountReceivableForm;
use App\Filament\Resources\AccountReceivables\Tables\AccountReceivablesTable;

class AccountReceivableResource extends Resource
{
    protected static ?string $model = AccountReceivable::class;
    
    // USAMOS A PROPRIEDADE EM VEZ DA FUNÇÃO PARA EVITAR ERROS DE ASSINATURA
    protected static ?string $slug = 'contas-a-receber';

    public static function getNavigationIcon(): string | \BackedEnum | null { return 'heroicon-o-banknotes'; }
    public static function getNavigationGroup(): ?string { return 'Financeiro'; }
    public static function getNavigationSort(): ?int { return 2; }
    public static function getModelLabel(): string { return 'Conta a Receber'; }
    public static function getPluralModelLabel(): string { return 'Contas a Receber'; }

    // (A pague a antiga linha: public static function getSlug(): string { return 'contas-a-receber'; })

    public static function form(Schema $schema): Schema { return AccountReceivableForm::configure($schema); }
    public static function table(Table $table): Table { return AccountReceivablesTable::configure($table); }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountReceivables::route('/'),
            'create' => Pages\CreateAccountReceivable::route('/create'),
            'edit' => Pages\EditAccountReceivable::route('/{record}/edit'),
        ];
    }
}