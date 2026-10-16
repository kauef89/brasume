<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Pages\Dashboard\Actions\FilterAction;
use App\Filament\Pages\Schemas\BillingFiltersSchema;

class BillingDashboard extends Dashboard
{
    use HasFiltersAction; // 🚀 Substitui o HasFiltersForm

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Faturamento (BI)';
    protected static ?string $title = 'Inteligência de Faturamento';
    protected static ?int $navigationSort = 1;

    /**
     * Move os filtros para uma Drawer acionada via Header Action
     */
    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label('Filtros e Ferramentas')
                ->icon('heroicon-m-adjustments-horizontal')
                ->color('primary')
                ->slideOver() // 🚀 Transforma o modal numa Drawer lateral!
                ->form(BillingFiltersSchema::getFormSchema()),
        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\BillingStatsWidget::class,
            \App\Filament\Widgets\BillingChartWidget::class,
            \App\Filament\Widgets\BillingComparativeChartWidget::class,
        ];
    }
}
