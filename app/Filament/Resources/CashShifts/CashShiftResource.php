<?php

namespace App\Filament\Resources\CashShifts;

use App\Filament\Resources\CashShifts\Pages;
use App\Models\CashShift;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

use App\Filament\Resources\CashShifts\Schemas\CashShiftForm;
use App\Filament\Resources\CashShifts\Tables\CashShiftTable;

class CashShiftResource extends Resource
{
    protected static ?string $model = CashShift::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Vendas & Atendimento';
    protected static ?string $slug = 'turnos-de-caixa';

    public static function getNavigationIcon(): string | \BackedEnum | null { return 'heroicon-o-inbox-stack'; }
    public static function getNavigationGroup(): ?string { return 'Vendas'; }
    public static function getNavigationSort(): ?int { return 1; }
    public static function getModelLabel(): string { return 'Turno de Caixa'; }
    public static function getPluralModelLabel(): string { return 'Turnos de Caixa'; }

    public static function form(Schema $schema): Schema
    {
        return CashShiftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashShiftTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            // Como é ManageRecords, só precisamos do index!
            'index' => Pages\ManageCashShifts::route('/'),
        ];
    }
}
