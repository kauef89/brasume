<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Services\Analytics\BillingService;

class BillingStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // 🚀 Garante array vazio caso os filtros ainda não tenham sido inicializados
        $filters = $this->filters ?? [];

        $data = BillingService::getConsolidatedDailyRevenue($filters);

        $totalRevenue = $data->sum('total_revenue');
        $totalOrders = $data->sum('total_orders');
        $ticketMedio = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;

        return [
            Stat::make('Faturamento Total', 'R$ ' . number_format($totalRevenue, 2, ',', '.'))
                ->description('Visão Executiva Consolidada')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Volume de Pedidos', number_format($totalOrders, 0, ',', '.'))
                ->description('Pedidos finalizados no período')
                ->color('primary'),

            Stat::make('Ticket Médio', 'R$ ' . number_format($ticketMedio, 2, ',', '.'))
                ->description('Gasto médio por pedido')
                ->color('info'),
        ];
    }
}
