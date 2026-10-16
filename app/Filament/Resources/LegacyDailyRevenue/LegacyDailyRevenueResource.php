<?php

namespace App\Filament\Resources\LegacyDailyRevenue;

use App\Models\LegacyDailyRevenue;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use App\Filament\Resources\LegacyDailyRevenue\Tables\LegacyDailyRevenuesTable;
use App\Filament\Resources\LegacyDailyRevenue\Pages\ManageLegacyDailyRevenues;

class LegacyDailyRevenueResource extends Resource
{
    protected static ?string $model = LegacyDailyRevenue::class;
    protected static string|\BackedEnum|null $navigationIcon = "heroicon-o-presentation-chart-line";
    protected static string|\UnitEnum|null $navigationGroup = 'Sistema Legado';
    protected static ?string $navigationLabel = "Faturamento Histórico (V0)";
    protected static ?string $modelLabel = "Faturamento Diário";
    protected static ?string $pluralModelLabel = "Faturamentos Diários";
    protected static ?int $navigationSort = 1;

    // Formulário manual (caso ele queira editar ou adicionar um dia manualmente à mão)
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make("date")
                ->label("Data")
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make("total_revenue")
                ->label("Total Faturado")
                ->numeric()
                ->required()
                ->prefix('R$'),
            TextInput::make("order_count")->label("Qtd de Pedidos")->numeric(),
            TextInput::make("expenses")
                ->label("Despesas/Custos")
                ->numeric()
                ->prefix('R$'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return LegacyDailyRevenuesTable::build($table);
    }

    public static function getPages(): array
    {
        return [
            "index" => ManageLegacyDailyRevenues::route("/"),
        ];
    }
}
