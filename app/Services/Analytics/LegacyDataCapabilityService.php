<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Cache;
use App\Models\LegacyDailyRevenue;
use App\Models\LegacyOrder;
use App\Models\Order;
use Carbon\Carbon;

class LegacyDataCapabilityService
{
    public static function analyze(): array
    {
        $tenantId = tenant('id') ?? 'global';

        // O cache garante que essa varredura profunda não pese a cada clique do usuário
        return Cache::remember("tenant_{$tenantId}_data_capabilities", 300, function () {

            $v0Min = LegacyDailyRevenue::min('date');
            $v0Max = LegacyDailyRevenue::max('date');

            $v1Min = LegacyOrder::min('issue_date');
            $v1Max = LegacyOrder::max('issue_date');

            $v3Min = Order::min('created_at');
            $v3Max = Order::max('created_at');

            // Fronteira do BI de Alta Fidelidade (Heatmap, etc)
            $detailedMinDates = array_filter([$v1Min, $v3Min]);
            $detailedStartDate = empty($detailedMinDates) ? null : min($detailedMinDates);

            // 🚀 Fronteiras Absolutas da Empresa (A primeira e a última venda de sempre)
            $allMinDates = array_filter([$v0Min, $v1Min, $v3Min]);
            $absoluteMinDate = empty($allMinDates) ? now()->toDateString() : Carbon::parse(min($allMinDates))->toDateString();

            $allMaxDates = array_filter([$v0Max, $v1Max, $v3Max]);
            $absoluteMaxDate = empty($allMaxDates) ? now()->toDateString() : Carbon::parse(max($allMaxDates))->toDateString();

            return [
                'has_v0' => !is_null($v0Min),
                'v0_start' => $v0Min ? Carbon::parse($v0Min) : null,
                'v0_end' => $v0Max ? Carbon::parse($v0Max) : null,

                'has_v1' => !is_null($v1Min),
                'v1_start' => $v1Min ? Carbon::parse($v1Min) : null,
                'v1_end' => $v1Max ? Carbon::parse($v1Max) : null,

                'has_detailed_data' => !is_null($detailedStartDate),
                'detailed_start_date' => $detailedStartDate ? Carbon::parse($detailedStartDate) : null,

                // Injetamos as fronteiras reais aqui!
                'absolute_min_date' => $absoluteMinDate,
                'absolute_max_date' => $absoluteMaxDate,
            ];
        });
    }

    public static function getDetailedCutoffMessage($filterStartDate): ?string
    {
        $capabilities = self::analyze();

        if (!$capabilities['has_detailed_data']) {
            return "O seu histórico atual contém apenas totais diários consolidados. Dados detalhados estarão disponíveis conforme você processa novos pedidos no sistema.";
        }

        $cutoffDate = $capabilities['detailed_start_date'];
        $filterDate = Carbon::parse($filterStartDate);

        if ($filterDate->lessThan($cutoffDate)) {
            return "Há dados detalhados para este gráfico somente a partir de " . $cutoffDate->format('d/m/Y') . ".";
        }

        return null;
    }
}
