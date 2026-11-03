<?php

namespace App\Filament\Resources\Payrolls;

use App\Filament\Resources\Payrolls\Pages;
use App\Filament\Resources\Payrolls\Schemas\PayrollForm;
use App\Filament\Resources\Payrolls\Schemas\PayrollInfolist;
use App\Filament\Resources\Payrolls\Tables\PayrollTable;
use App\Filament\Resources\Payrolls\RelationManagers\PayslipsRelationManager;
use App\Models\Payroll;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PayrollResource extends Resource
{
    protected static ?string $model = Payroll::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|\UnitEnum|null $navigationGroup = 'Gestão & RH';
    protected static ?string $modelLabel = 'Folha de Pagamento';
    protected static ?string $pluralModelLabel = 'Folhas de Pagamento';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PayrollForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PayrollInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollTable::table($table);
    }

    public static function getRelations(): array
    {
        return [
            PayslipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrolls::route('/'),
            'view'  => Pages\ViewPayroll::route('/{record}'),
        ];
    }
}
