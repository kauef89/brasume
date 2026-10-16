<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Services\Analytics\BillingService;
use App\Services\Analytics\LegacyDataCapabilityService;
use Carbon\Carbon;

class BillingComparativeChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Comparativo de Crescimento (Ano a Ano)';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    // 🚀 CONSISTÊNCIA PANORÂMICA
    protected ?string $maxHeight = '220px';

    protected function getData(): array
    {
        $filters = $this->filters;
        $startDateStr = $filters['startDate'] ?? Carbon::now()->subDays(30)->toDateString();
        $endDateStr = $filters['endDate'] ?? Carbon::now()->toDateString();

        $startDate = Carbon::parse($startDateStr);
        $endDate = Carbon::parse($endDateStr);

        $capabilities = LegacyDataCapabilityService::analyze();
        $minDateStr = $capabilities['v0_start'] ?? ($capabilities['v1_start'] ?? '2020-01-01');
        $minYear = Carbon::parse($minDateStr)->year;

        $currentYear = $endDate->year;
        $labels = [];
        $revenueData = [];

        for ($year = $minYear; $year <= $currentYear; $year++) {
            $yearStart = $startDate->copy()->year($year)->toDateString();
            $yearEnd = $endDate->copy()->year($year)->toDateString();

            $yearFilters = $filters;
            $yearFilters['startDate'] = $yearStart;
            $yearFilters['endDate'] = $yearEnd;

            $data = BillingService::getConsolidatedDailyRevenue($yearFilters);

            $labels[] = (string) $year;
            $revenueData[] = round($data->sum('total_revenue'), 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Faturamento Acumulado no Período (R$)',
                    'data' => $revenueData,
                    'backgroundColor' => '#f59e0b',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
