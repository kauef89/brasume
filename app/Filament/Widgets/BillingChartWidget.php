<?php

namespace App\Filament\Widgets;

use App\Services\BillingConsolidator;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class BillingChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;
    protected ?string $heading = "Evolução do Faturamento";
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    // 🚀 AJUSTE 2: Reduzindo a altura do gráfico (o padrão do Filament costuma ser 400px)
    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDateStr = $this->filters['startDate'] ?? now()->subDays(30)->toDateString();
        $endDateStr = $this->filters['endDate'] ?? now()->toDateString();
        $compareWith = $this->filters['compare_with'] ?? 'none';
        $groupBy = $this->filters['group_by'] ?? 'day';
        $stratifyBy = $this->filters['stratify_by'] ?? 'none';

        $filterType = $this->filters['order_type'] ?? null;
        $filterPayment = $this->filters['payment_method'] ?? null;

        $data = BillingConsolidator::getDashboardData($startDateStr, $endDateStr, $compareWith, $groupBy, $stratifyBy, $filterType, $filterPayment);

        $currentSeries = is_array($data['current_series']) ? $data['current_series'] : collect($data['current_series'])->toArray();
        $previousSeries = is_array($data['previous_series']) ? $data['previous_series'] : collect($data['previous_series'])->toArray();

        $labels = [];
        $datasets = [];

        // 1. MONTAR AS DATAS (Eixo X)
        if ($groupBy === 'day') {
            $period = CarbonPeriod::create($startDateStr, $endDateStr);
            foreach ($period as $date) {
                $labels[] = $date->format("d/m");
            }
        } else {
            $labels = array_keys($currentSeries);
        }

        // 2. MONTAR OS DATASETS
        if ($stratifyBy === 'none') {
            // COMPORTAMENTO NORMAL
            $currentData = [];
            $previousData = [];

            foreach ($currentSeries as $key => $values) {
                $currentData[] = $values['revenue'] ?? 0;
            }
            foreach ($previousSeries as $key => $values) {
                $previousData[] = $values['revenue'] ?? 0;
            }

            $datasets[] = [
                'label' => 'Faturamento Atual (R$)',
                'data' => $currentData,
                'backgroundColor' => '#10b981', // Verde sólido
                'borderRadius' => 4,
            ];

            // 🚀 AJUSTE 1: Barras Lado a Lado para Comparação
            if ($data['is_comparing'] && !empty($previousData)) {
                $datasets[] = [
                    'label' => 'Período Anterior (R$)',
                    'data' => $previousData,
                    'backgroundColor' => '#d1d5db', // Cinza claro para não "roubar" a atenção da barra verde
                    'borderRadius' => 4,
                    // Removemos o 'type' => 'line' e as configurações de borda tracejada!
                ];
            }
        } else {
            // COMPORTAMENTO ESTRATIFICADO (Barras Empilhadas)
            $colors = [
                '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6', '#64748b'
            ];
            $colorIndex = 0;

            $stratifiedSeries = is_array($data['stratified_series']) ? $data['stratified_series'] : collect($data['stratified_series'])->toArray();

            foreach ($stratifiedSeries as $groupName => $valuesArray) {
                $datasets[] = [
                    'label' => ucfirst($groupName),
                    'data' => array_values($valuesArray),
                    'backgroundColor' => $colors[$colorIndex % count($colors)],
                    'borderRadius' => 0,
                ];
                $colorIndex++;
            }
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        $stratifyBy = $this->filters['stratify_by'] ?? 'none';

        return [
            'scales' => [
                'x' => [
                    'stacked' => $stratifyBy !== 'none',
                ],
                'y' => [
                    'stacked' => $stratifyBy !== 'none',
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            // 🚀 Garante que o Chart.js respeite a nossa altura de 250px em vez de tentar ficar quadrado
            'maintainAspectRatio' => false,
        ];
    }
}
