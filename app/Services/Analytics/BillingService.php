<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BillingService
{
    /**
     * Retorna o faturamento diário reagindo aos filtros avançados.
     */
    public static function getConsolidatedDailyRevenue(array $filters = [])
    {
        $startDate = $filters['startDate'] ?? Carbon::now()->subDays(30)->toDateString();
        $endDate = $filters['endDate'] ?? Carbon::now()->toDateString();

        $orderTypes = $filters['order_types'] ?? [];
        $paymentMethods = $filters['payment_methods'] ?? [];

        // Se houver filtros avançados, a V0 (consolidação pura) não consegue responder
        $hasAdvancedFilters = !empty($orderTypes) || !empty($paymentMethods);

        // ---------------------------------------------------------
        // 1. BASE V3 (A atual e mais rica)
        // ---------------------------------------------------------
        $queryV3 = DB::table('orders as v3')
            ->selectRaw("DATE(v3.created_at) as report_date, SUM(v3.total) as total_revenue, COUNT(v3.id) as total_orders, 'v3' as source")
            ->whereIn('v3.status', ['closed', 'paid', 'completed'])
            ->whereBetween('v3.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if (!empty($orderTypes)) {
            $queryV3->whereIn('v3.type', $orderTypes); // Assumindo 'type' ou 'order_type' na sua base
        }

        if (!empty($paymentMethods)) {
            $queryV3->whereExists(function ($q) use ($paymentMethods) {
                $q->select(DB::raw(1))
                  ->from('payments') // Tabela de pagamentos da V3
                  ->whereColumn('payments.order_id', 'v3.id')
                  ->whereIn('payments.payment_method_id', $paymentMethods);
            });
        }
        $queryV3->groupByRaw("DATE(v3.created_at)");

        // ---------------------------------------------------------
        // 2. BASE V1 (O Legado Rico - Esmagada pela V3 em caso de conflito)
        // ---------------------------------------------------------
        $queryV1 = DB::table('legacy_orders as v1')
            ->selectRaw("DATE(v1.issue_date) as report_date, SUM(v1.total_value) as total_revenue, COUNT(v1.id) as total_orders, 'v1' as source")
            ->whereIn('v1.status', ['processed', 'completed', 'authorized'])
            ->whereBetween('v1.issue_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('orders as v3')
                    ->whereRaw("DATE(v3.created_at) = DATE(v1.issue_date)");
            });

        if (!empty($orderTypes)) {
            $queryV1->whereIn('v1.order_type', $orderTypes);
        }

        if (!empty($paymentMethods)) {
            $queryV1->whereExists(function ($q) use ($paymentMethods) {
                $q->select(DB::raw(1))
                  ->from('legacy_order_payments as lop')
                  ->join('legacy_payment_methods as lpm', 'lop.legacy_payment_method_id', '=', 'lpm.id')
                  ->whereColumn('lop.legacy_order_id', 'v1.id')
                  ->whereIn('lpm.current_payment_method_id', $paymentMethods);
            });
        }
        $queryV1->groupByRaw("DATE(v1.issue_date)");

        // ---------------------------------------------------------
        // 3. BASE V0 (A Caderneta - Excluída se houver filtro avançado)
        // ---------------------------------------------------------
        $queryV0 = DB::table('legacy_daily_revenues as v0')
            ->selectRaw("DATE(v0.date) as report_date, SUM(v0.total_revenue) as total_revenue, SUM(v0.order_count) as total_orders, 'v0' as source")
            ->whereBetween('v0.date', [$startDate, $endDate])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('orders as v3')
                    ->whereRaw("DATE(v3.created_at) = DATE(v0.date)");
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('legacy_orders as v1')
                    ->whereRaw("DATE(v1.issue_date) = DATE(v0.date)");
            });

        // 🚀 A REGRA DE OURO DO BI:
        if ($hasAdvancedFilters) {
            $queryV0->whereRaw('1 = 0'); // Força a exclusão total da V0 na query
        }

        $queryV0->groupByRaw("DATE(v0.date)");

        // ---------------------------------------------------------
        // CONSOLIDAÇÃO FINAL
        // ---------------------------------------------------------
        return DB::query()
            ->fromSub($queryV3->unionAll($queryV1)->unionAll($queryV0), 'consolidated')
            ->orderBy('report_date', 'asc')
            ->get();
    }
}
