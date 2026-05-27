<?php

namespace App\Filament\Resources\LegacyCustomers;

use App\Models\LegacyCustomer;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use App\Filament\Resources\LegacyCustomers\Pages\ManageLegacyCustomers;
use App\Filament\Resources\LegacyCustomers\Schemas\LegacyCustomerForm;
use App\Filament\Resources\LegacyCustomers\Tables\LegacyCustomersTable;

class LegacyCustomerResource extends Resource
{
    protected static ?string $model = LegacyCustomer::class;
    protected static string|\BackedEnum|null $navigationIcon = "heroicon-o-users";
    protected static string|\UnitEnum|null $navigationGroup = "Legado Fiscal";
    protected static ?string $modelLabel = "Cliente Antigo";
    protected static ?string $pluralModelLabel = "Clientes Antigos";

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = static::getModel()::whereNull("customer_id")->count();
            return $count > 0 ? (string) $count : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return "warning";
    }

    public static function form(Schema $schema): Schema
    {
        return LegacyCustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LegacyCustomersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            "index" => ManageLegacyCustomers::route("/"),
        ];
    }
}
