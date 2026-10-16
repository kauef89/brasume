<?php

namespace App\Services\Marketing;

use App\Models\CompanySetting;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Customer;
use App\Models\OrderRating;

class CrmService
{
    public function getDashboardData(): array
    {
        $settings = CompanySetting::getCached();
        $startOfMonth = now()->startOfMonth();

        // 1. Métricas Gerais
        $totalDiscount = Order::where('created_at', '>=', $startOfMonth)->sum('discount');
        $salesFromCoupons = Order::whereNotNull('coupon_id')->where('created_at', '>=', $startOfMonth)->sum('total');

        // 2. Garimpo de Oportunidades (Aniversários e Churn)
        $opportunities = collect();

        $birthdays = Customer::whereNotNull('birth_date')
            ->whereMonth('birth_date', now()->month)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'reason' => 'birthday',
                    'last_order_days' => $c->orders()->latest('created_at')->first()?->created_at->diffInDays(now()) ?? 'Muitos'
                ];
            });
        $opportunities = $opportunities->merge($birthdays);

        $churnThreshold = $settings->churn_days_threshold ?? 30;
        $inactiveCustomers = Customer::whereHas('orders', function ($q) use ($churnThreshold) {
                $q->where('created_at', '<', now()->subDays($churnThreshold));
            })
            ->whereDoesntHave('orders', function ($q) use ($churnThreshold) {
                $q->where('created_at', '>=', now()->subDays($churnThreshold));
            })
            ->whereNotNull('phone')
            ->take(15)
            ->get()
            ->map(function ($c) use ($churnThreshold) {
                return [
                    'id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'reason' => 'churn',
                    'last_order_days' => $c->orders()->latest('created_at')->first()?->created_at->diffInDays(now()) ?? $churnThreshold
                ];
            });
        $opportunities = $opportunities->merge($inactiveCustomers);

        // 3. Avaliações (Order Ratings)
        $ratings = OrderRating::with('customer:id,name,phone')
            ->orderBy('created_at', 'desc')->take(50)->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id, 'order_id' => $r->order_id, 'customer_name' => $r->customer->name ?? 'Visitante',
                    'customer_phone' => $r->customer->phone ?? null, 'rating' => $r->rating,
                    'tags' => $r->tags ?? [], 'comment' => $r->comment, 'created_at' => $r->created_at->toIso8601String(),
                ];
            });

        // 4. Lista de Cupons de Marketing Direto (Ignoramos os VEM de growth aqui)
        $coupons = Coupon::where('code', 'not like', 'VEM%')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id, 'code' => $c->code, 'type' => $c->type, 'value' => (float) $c->value,
                    'is_active' => (bool) $c->is_active, 'expires_at' => $c->expires_at ? $c->expires_at->format('Y-m-d') : null,
                    'usage_count' => Order::where('coupon_id', $c->id)->count(),
                ];
            });

        // 🚀 5. MÓDULO DE GROWTH O2O (Onde a Mágica Financeira Acontece)

        // 5.1 Fidelidade e Cashback
        // Calcula a moeda virtual: Pontos ganhos vs Valor Real de conversão
        $conversionRate = $settings->loyalty_cashback_conversion_rate ?? 5;

        // 🚀 CORREÇÃO: Puxa o saldo exato que os clientes têm na carteira HOJE
        $totalPointsOutstanding = Customer::sum('loyalty_points') ?? 0;
        $cashbackLiability = ($totalPointsOutstanding / 100) * $conversionRate; // Passivo financeiro em aberto

        // O que já foi utilizado historicamente
        $cashbackUsed = Order::sum('cashback_used') ?? 0;

        // O Total distribuído é a soma do que já usaram com o que ainda guardam!
        $cashbackGiven = $cashbackLiability + $cashbackUsed;

        // 5.2 Rastreio de Conversão de Flyers (Indicação)
        $growthCoupons = Coupon::where('code', 'like', 'VEM%')->get();
        $flyersPrinted = $growthCoupons->count(); // 1 Cupom "VEM" = 1 Flyer impresso e grampeado na sacola

        $growthCouponIds = $growthCoupons->pluck('id');
        $flyersConverted = Order::whereIn('coupon_id', $growthCouponIds)->count();
        $flyerDiscountsGiven = Order::whereIn('coupon_id', $growthCouponIds)->sum('discount');

        return [
            'metrics' => [
                'totalDiscount' => (float) $totalDiscount,
                'salesFromCoupons' => (float) $salesFromCoupons,
            ],
            'opportunities' => $opportunities->values(),
            'settings' => [
                'auto_birthday_coupon' => (bool) ($settings->auto_birthday_coupon ?? false),
                'auto_churn_coupon' => (bool) ($settings->auto_churn_coupon ?? false),
            ],
            'ratings' => $ratings,
            'coupons' => $coupons,

            // 🚀 Injetando os dados crus para a CrmGrowthTab processar
            'growth' => [
                'cashback_given' => (float) $cashbackGiven,
                'cashback_used' => (float) $cashbackUsed,
                'cashback_liability' => (float) $cashbackLiability, // 🚀 O Front vai ler isso direto!
                'flyers_printed' => $flyersPrinted,
                'flyers_converted' => $flyersConverted,
                'flyer_discounts_given' => (float) $flyerDiscountsGiven,
            ]
        ];
    }

    public function toggleAutomation(string $key, bool $value): void
    {
        $settings = CompanySetting::getCached();
        if ($settings) {
            $settings->update([$key => $value]);
        }
    }

    public function createCoupon(array $data): Coupon
    {
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = true;

        if (empty($data['value'])) {
            $data['value'] = 0;
        }

        return Coupon::create($data);
    }
}
