<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class BillingConsolidator
{
    public static function getDashboardData(
        string $startDate, string $endDate, string $compareWith,
        string $groupBy, string $stratifyBy,
        array|string|null $filterType = null,
        array|string|null $filterPayment = null
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // 1. DADOS DO PERÍODO ATUAL
        $currentRaw = self::fetchData($start, $end, $filterType, $filterPayment);
        $currentRaw = self::applyStratification($currentRaw, $stratifyBy);

        $currentTotals = [
            'revenue' => $currentRaw->sum('revenue'),
            'orders' => $currentRaw->sum('order_count'),
        ];
        $currentTotals['avg_ticket'] = $currentTotals['orders'] > 0 ? ($currentTotals['revenue'] / $currentTotals['orders']) : 0;

        // 2. DADOS DO PERÍODO ANTERIOR
        $previousTotals = ['revenue' => 0, 'orders' => 0, 'avg_ticket' => 0];
        $deltas = ['revenue' => 0, 'orders' => 0, 'avg_ticket' => 0];
        $previousGrouped = [];

        $isComparing = ($compareWith !== 'none' && $stratifyBy === 'none');

        if ($isComparing) {
            $prevStart = $start->copy();
            $prevEnd = $end->copy();

            if ($compareWith === 'previous_period') {
                $diffInDays = $start->diffInDays($end) + 1;
                $prevStart->subDays($diffInDays);
                $prevEnd->subDays($diffInDays);
            } elseif ($compareWith === 'previous_year') {
                $prevStart->subYear();
                $prevEnd->subYear();
            }

            $previousRaw = self::fetchData($prevStart, $prevEnd, $filterType, $filterPayment);
            $previousRaw = self::applyStratification($previousRaw, 'none');
            $previousGrouped = self::groupData($previousRaw, $groupBy, $prevStart, $prevEnd);

            $previousTotals['revenue'] = $previousRaw->sum('revenue');
            $previousTotals['orders'] = $previousRaw->sum('order_count');
            $previousTotals['avg_ticket'] = $previousTotals['orders'] > 0 ? ($previousTotals['revenue'] / $previousTotals['orders']) : 0;

            $deltas['revenue'] = self::calculateDelta($currentTotals['revenue'], $previousTotals['revenue']);
            $deltas['orders'] = self::calculateDelta($currentTotals['orders'], $previousTotals['orders']);
            $deltas['avg_ticket'] = self::calculateDelta($currentTotals['avg_ticket'], $previousTotals['avg_ticket']);
        }

        // 3. ESTRUTURAÇÃO PARA O GRÁFICO
        $currentSeries = [];
        $stratifiedSeries = [];

        if ($stratifyBy === 'none') {
            $currentSeries = self::groupData($currentRaw, $groupBy, $start, $end);
        } else {
            $stratifiedSeries = self::groupStratifiedData($currentRaw, $groupBy, $start, $end);
        }

        return [
            'current_totals' => $currentTotals,
            'previous_totals' => $previousTotals,
            'deltas' => $deltas,
            'current_series' => $currentSeries,
            'previous_series' => $previousGrouped,
            'stratified_series' => $stratifiedSeries,
            'is_comparing' => $isComparing,
        ];
    }

    /**
     * Motor de Extração: Simples, Limpo e Seguro
     */
    private static function fetchData(Carbon $start, Carbon $end, array|string|null $filterType, array|string|null $filterPayment = null): \Illuminate\Support\Collection
    {
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();
        $data = collect();

        // Normalização dos Arrays
        $typeArray = !empty($filterType) ? (is_array($filterType) ? $filterType : [$filterType]) : [];
        $paymentArray = !empty($filterPayment) ? (is_array($filterPayment) ? $filterPayment : [$filterPayment]) : [];

        // ==========================================
        // 🚀 O TRADUTOR DE PAGAMENTOS
        // ==========================================
        $hasNullPayment = in_array('null_payment', $paymentArray);
        $cleanPaymentIds = array_filter(array_diff($paymentArray, ['null_payment']));

        $v3PaymentNames = [];
        $legacyPaymentIds = [];

        if (!empty($cleanPaymentIds)) {
            // Pega o nome real do pagamento para procurar nas colunas varchar da V3
            $v3PaymentNames = DB::table('payment_methods')
                ->whereIn('id', $cleanPaymentIds)
                ->pluck('name')
                ->toArray();

            // Pega o ID antigo mapeado para filtrar no histórico V1/V2
            $legacyPaymentIds = DB::table('legacy_payment_methods')
                ->whereIn('current_payment_method_id', $cleanPaymentIds)
                ->pluck('id')
                ->toArray();
        }

        // Array híbrido para a V3 (Busca pelo ID em string ou pelo Nome real)
        $v3SearchTerms = array_merge($cleanPaymentIds, $v3PaymentNames);


        // ==========================================
        // DADOS V3
        // ==========================================
        $v3 = DB::table('orders')
            ->where(function($query) {
                $query->whereNotIn('status', ['cancelled', 'canceled', 'rejected', 'pending'])
                      ->orWhereNull('status');
            })
            ->whereBetween('created_at', [$start, $end]);

        // Filtro de Tipo V3
        if (!empty($typeArray)) {
            $v3->where(function($query) use ($typeArray) {
                if (in_array('null_type', $typeArray)) {
                    $cleanTypes = array_diff($typeArray, ['null_type']);
                    $query->whereNull('type');
                    if (!empty($cleanTypes)) $query->orWhereIn('type', $cleanTypes);
                } else {
                    $query->whereIn('type', $typeArray);
                }
            });
        }

        // Filtro de Pagamento V3
        if (!empty($paymentArray)) {
            $v3->where(function($query) use ($hasNullPayment, $v3SearchTerms) {
                if ($hasNullPayment) {
                    $query->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))->from('order_payments')->whereRaw('order_payments.order_id = orders.id');
                    })->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))->from('payments')->whereRaw('payments.order_id = orders.id');
                    });
                }

                if (!empty($v3SearchTerms)) {
                    // Cobre as duas tabelas de pagamento possíveis na V3
                    $query->orWhereExists(function ($q) use ($v3SearchTerms) {
                        $q->select(DB::raw(1))
                          ->from('order_payments')
                          ->whereRaw('order_payments.order_id = orders.id')
                          ->whereIn('order_payments.payment_method', $v3SearchTerms);
                    })->orWhereExists(function ($q) use ($v3SearchTerms) {
                        $q->select(DB::raw(1))
                          ->from('payments')
                          ->whereRaw('payments.order_id = orders.id')
                          ->whereIn('payments.method', $v3SearchTerms);
                    });
                }
            });
        }

        $v3Data = $v3->selectRaw("DATE(created_at) as raw_date, type as order_type, SUM(total) as revenue, COUNT(id) as order_count")
                     ->groupBy('raw_date', 'order_type')->get();
        $data = $data->merge($v3Data);


        // ==========================================
        // DADOS V1 e V2 (Histórico)
        // ==========================================
        $v1 = DB::table('legacy_orders')
            ->where(function($query) {
                $query->whereNotIn('status', ['canceled', 'cancelled', 'rejected'])
                      ->orWhereNull('status');
            })
            ->whereBetween('issue_date', [$start, $end]);

        // Filtro de Tipo Legado
        if (!empty($typeArray)) {
            $v1->where(function($query) use ($typeArray) {
                if (in_array('null_type', $typeArray)) {
                    $cleanTypes = array_diff($typeArray, ['null_type']);
                    $query->whereNull('order_type');
                    if (!empty($cleanTypes)) $query->orWhereIn('order_type', $cleanTypes);
                } else {
                    $query->whereIn('order_type', $typeArray);
                }
            });
        }

        // Filtro de Pagamento Legado
        if (!empty($paymentArray)) {
            $v1->where(function($query) use ($hasNullPayment, $legacyPaymentIds) {
                if ($hasNullPayment) {
                    $query->whereNotExists(function ($q) {
                        $q->select(DB::raw(1))
                          ->from('legacy_order_payments')
                          ->whereRaw('legacy_order_payments.legacy_order_id = legacy_orders.id');
                    });
                }

                if (!empty($legacyPaymentIds)) {
                    $query->orWhereExists(function ($q) use ($legacyPaymentIds) {
                        $q->select(DB::raw(1))
                          ->from('legacy_order_payments')
                          ->whereRaw('legacy_order_payments.legacy_order_id = legacy_orders.id')
                          ->whereIn('legacy_order_payments.legacy_payment_method_id', $legacyPaymentIds);
                    });
                }
            });
        }

        $v1Data = $v1->selectRaw("DATE(issue_date) as raw_date, order_type, SUM(total_value) as revenue, COUNT(id) as order_count")
                     ->groupBy('raw_date', 'order_type')->get();
        $data = $data->merge($v1Data);


        // ==========================================
        // DADOS V0 (Legado Consolidado Diário)
        // ==========================================
        if ((empty($typeArray) || in_array('null_type', $typeArray)) && (empty($paymentArray) || in_array('null_payment', $paymentArray))) {
            $v0 = DB::table('legacy_daily_revenues')
                ->whereBetween('date', [$startStr, $endStr])
                ->selectRaw("date as raw_date, 'Consolidado' as order_type, total_revenue as revenue, IFNULL(order_count, 0) as order_count")
                ->get();
            $data = $data->merge($v0);
        }

        return $data;
    }

    /**
     * Limpa as Datas e Classifica os Tipos de Venda
     */
    private static function applyStratification(\Illuminate\Support\Collection $data, string $stratifyBy): \Illuminate\Support\Collection
    {
        return $data->map(function ($item) use ($stratifyBy) {
            $item->raw_date = Carbon::parse($item->raw_date)->format('Y-m-d');

            if ($stratifyBy === 'type') {
                $t = strtolower($item->order_type ?? '');

                $item->strat_key = match (true) {
                    $t === '' || $t === 'null' => 'Sem Tipo Mapeado 🪹',
                    str_contains($t, 'delivery') => 'Delivery',
                    str_contains($t, 'table') || str_contains($t, 'mesa') => 'Mesa',
                    str_contains($t, 'counter') || str_contains($t, 'balcao') || str_contains($t, 'balcão') => 'Balcão',
                    $t === 'consolidado' => 'Legado S/ Tipo',
                    default => 'Outros',
                };
            } else {
                $item->strat_key = 'Geral';
            }
            return $item;
        });
    }

    /**
     * Agrupamento para Série Simples
     */
    private static function groupData(\Illuminate\Support\Collection $rawData, string $groupBy, Carbon $start, Carbon $end): array
    {
        $format = match ($groupBy) {
            'month' => 'Y-m',
            'week' => 'Y-W',
            default => 'Y-m-d',
        };

        $grouped = $rawData->groupBy(function ($item) use ($format) {
            return Carbon::parse($item->raw_date)->format($format);
        })->map(function ($group) {
            return [
                'revenue' => $group->sum('revenue'),
                'order_count' => $group->sum('order_count')
            ];
        })->toArray();

        $series = [];
        if ($groupBy === 'day') {
            foreach (CarbonPeriod::create($start, $end) as $date) {
                $key = $date->format($format);
                $series[$key] = $grouped[$key] ?? ['revenue' => 0, 'order_count' => 0];
            }
        } else {
            ksort($grouped);
            $series = $grouped;
        }

        return $series;
    }

    /**
     * Agrupamento para Série Estratificada (Barras Empilhadas)
     */
    private static function groupStratifiedData(\Illuminate\Support\Collection $rawData, string $groupBy, Carbon $start, Carbon $end): array
    {
        $format = match ($groupBy) {
            'month' => 'Y-m',
            'week' => 'Y-W',
            default => 'Y-m-d',
        };

        $periodKeys = [];
        if ($groupBy === 'day') {
            foreach (CarbonPeriod::create($start, $end) as $date) {
                $periodKeys[$date->format($format)] = 0;
            }
        } else {
            $uniqueKeys = $rawData->map(fn($i) => Carbon::parse($i->raw_date)->format($format))->unique()->sort()->values();
            foreach ($uniqueKeys as $k) {
                $periodKeys[$k] = 0;
            }
        }

        $series = [];
        $groupedByKey = $rawData->groupBy('strat_key');

        foreach ($groupedByKey as $stratKey => $items) {
            $timeValues = $periodKeys;
            foreach ($items as $item) {
                $k = Carbon::parse($item->raw_date)->format($format);
                if (isset($timeValues[$k])) {
                    $timeValues[$k] += $item->revenue;
                }
            }
            $series[$stratKey] = array_values($timeValues);
        }

        return $series;
    }

    private static function calculateDelta($current, $previous): float
    {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return (($current - $previous) / $previous) * 100;
    }
}
