<?php

namespace App\Filament\Widgets;

use App\Services\BillingConsolidator;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class BillingStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    protected static bool $isDiscovered = false;
    protected static ?int $sort = 1;

    protected function getStats(): array
        {
            $startDate = $this->filters['startDate'] ?? now()->subDays(30)->toDateString();
            $endDate = $this->filters['endDate'] ?? now()->toDateString();
            $compareWith = $this->filters['compare_with'] ?? 'none';
            $groupBy = $this->filters['group_by'] ?? 'day';

            // 🚀 AQUI ESTÃO OS NOVOS FILTROS QUE FALTAVAM
            $stratifyBy = $this->filters['stratify_by'] ?? 'none';
            $filterType = $this->filters['order_type'] ?? null;
            $filterPayment = $this->filters['payment_method'] ?? null;

            // 🚀 AGORA PASSAMOS OS 7 ARGUMENTOS CORRETAMENTE PARA O MOTOR
            $data = BillingConsolidator::getDashboardData(
                $startDate,
                $endDate,
                $compareWith,
                $groupBy,
                $stratifyBy,
                $filterType,
                $filterPayment
            );

            $current = $data['current_totals'];
            $deltas = $data['deltas'];
            $isComparing = $data['is_comparing'];

            // Função auxiliar para montar a descrição e cor do card
            $formatDelta = function ($delta, $prevValue, $isCurrency = false) use ($isComparing) {
                if (!$isComparing) return ['text' => 'Período selecionado', 'color' => 'gray', 'icon' => null];

                // Formata o valor antigo para exibir no card
                $formattedPrev = $isCurrency
                    ? 'R$ ' . number_format($prevValue, 2, ',', '.')
                    : number_format($prevValue, 0, '', '.');

                $formattedPct = number_format(abs($delta), 1, ',', '.') . '%';

                if ($delta > 0) return ['text' => "+{$formattedPct} vs anterior ({$formattedPrev})", 'color' => 'success', 'icon' => 'heroicon-m-arrow-trending-up'];
                if ($delta < 0) return ['text' => "-{$formattedPct} vs anterior ({$formattedPrev})", 'color' => 'danger', 'icon' => 'heroicon-m-arrow-trending-down'];

                return ['text' => "Igual ao anterior ({$formattedPrev})", 'color' => 'gray', 'icon' => 'heroicon-m-minus'];
            };

            $revDelta = $formatDelta($deltas['revenue'], $data['previous_totals']['revenue'], true);
            $ordDelta = $formatDelta($deltas['orders'], $data['previous_totals']['orders'], false);
            $tktDelta = $formatDelta($deltas['avg_ticket'], $data['previous_totals']['avg_ticket'], true);

            // Mini gráficos de tendência (Sparklines) - Só funcionam se não houver estratificação
            $revenueTrend = [];
            $ordersTrend = [];
            if ($stratifyBy === 'none') {
                 $revenueTrend = collect($data['current_series'])->pluck('revenue')->toArray();
                 $ordersTrend = collect($data['current_series'])->pluck('order_count')->toArray();
            }

            return [
                Stat::make('Faturamento Total', 'R$ ' . number_format($current['revenue'], 2, ',', '.'))
                    ->description($revDelta['text'])
                    ->descriptionIcon($revDelta['icon'])
                    ->color($revDelta['color'])
                    ->chart(empty($revenueTrend) ? null : $revenueTrend),

                Stat::make('Total de Pedidos', number_format($current['orders'], 0, '', '.'))
                    ->description($ordDelta['text'])
                    ->descriptionIcon($ordDelta['icon'])
                    ->color($ordDelta['color'])
                    ->chart(empty($ordersTrend) ? null : $ordersTrend),

                Stat::make('Ticket Médio', 'R$ ' . number_format($current['avg_ticket'], 2, ',', '.'))
                    ->description($tktDelta['text'])
                    ->descriptionIcon($tktDelta['icon'])
                    ->color($tktDelta['color']),
            ];
        }
}
