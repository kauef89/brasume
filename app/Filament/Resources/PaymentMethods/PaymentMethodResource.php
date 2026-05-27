<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\PaymentMethods\Pages;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Resources\ResourceConfiguration;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

// Importando as nossas classes desacopladas
use App\Filament\Resources\PaymentMethods\Schemas\PaymentMethodForm;
use App\Filament\Resources\PaymentMethods\Tables\PaymentMethodsTable;
use Illuminate\Database\Eloquent\Model;
/**
 * @extends Resource<Model,ResourceConfiguration>
 */
class PaymentMethodResource extends Resource
{
    use HasGlobalSearch;
    protected static ?string $model = PaymentMethod::class;

    // Usando os Getters
    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return "heroicon-o-credit-card";
    }

    public static function getNavigationGroup(): ?string
    {
        return "Financeiro";
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getModelLabel(): string
    {
        return "Meio de Pagto.";
    }

    public static function getPluralModelLabel(): string
    {
        return "Meios de Pagto.";
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentMethodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentMethodsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            "index" => Pages\ListPaymentMethods::route("/"),
            "create" => Pages\CreatePaymentMethod::route("/create"),
            "edit" => Pages\EditPaymentMethod::route("/{record}/edit"),
        ];
    }
}
