<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Services\Analytics\BillingService;
use App\Services\Analytics\LegacyDataCapabilityService;
use Carbon\Carbon;

class BillingChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Evolução de Receita';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    // 🚀 REDUZINDO A ALTURA PARA UM VISUAL PANORÂMICO
    protected ?string $maxHeight = '220px';

    protected function getData(): array
    {
        // 🚀 Trata $this->filters nulo no ciclo inicial do Livewire
        $filters = $this->filters ?? [];

        $data = BillingService::getConsolidatedDailyRevenue($filters);

        $timeGrouping = $filters['time_grouping'] ?? 'day';

        // 🚀 AGRUPAMENTO INTELIGENTE (INCLUINDO ANO)
        $groupedData = collect($data)->groupBy(function ($row) use ($timeGrouping) {
            $date = Carbon::parse($row->report_date);
            return match ($timeGrouping) {
                'year' => $date->format('Y'),
                'week' => 'Sem. ' . $date->format('W / Y'),
                'month' => ucfirst($date->translatedFormat('M / Y')),
                default => $date->format('d/m/Y'),
            };
        });

        $labels = [];
        $revenueData = [];

        foreach ($groupedData as $label => $group) {
            $labels[] = $label;
            $revenueData[] = round($group->sum('total_revenue'), 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Faturamento Consolidado (R$)',
                    'data' => $revenueData,
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'borderColor' => '#10b981',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    // 🚀 MÁGICA DO EIXO X: Esconde as datas espremidas
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'ticks' => [
                        'autoSkip' => true,
                        'maxTicksLimit' => 12,
                        'maxRotation' => 0,
                    ],
                ],
            ],
        ];
    }

    protected function getEmptyStateDescription(): ?string
    {
        $startDate = $this->filters['startDate'] ?? null;

        $cutoffMessage = LegacyDataCapabilityService::getDetailedCutoffMessage($startDate);

        if ($cutoffMessage) {
            return $cutoffMessage;
        }

        return 'Não existem dados de faturamento (nem legados, nem atuais) registados para os filtros selecionados.';
    }
}
