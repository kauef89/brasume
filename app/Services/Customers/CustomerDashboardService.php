<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\LegacyOrder;
use App\Models\LegacyOrderItem;
use App\Models\PaymentMethod;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CustomerDashboardService
{
    public function getDashboardPayload(?User $user, ?int $guestId, array $allowedGuestAddresses = []): array
    {
        $targetCustomer = null;
        $isGuest = false;

        if ($user && $user->customer) {
            $targetCustomer = $user->customer;
        } elseif ($guestId) {
            $targetCustomer = Customer::find($guestId);
            if ($targetCustomer) {
                $isGuest = true;
            }
        }

        $customerPayload = null;
        $myActiveOrders = [];
        $myHistory = [];

        if ($targetCustomer) {
            if (!$isGuest) {
                $targetCustomer->load(['addresses' => function($q) {
                    $q->where('is_active', true)->with(['street', 'neighborhood']);
                }]);
            } else {
                $targetCustomer->load(['addresses' => function($q) use ($allowedGuestAddresses) {
                    $q->where('is_active', true)
                      ->whereIn('id', $allowedGuestAddresses)
                      ->with(['street', 'neighborhood']);
                }]);
            }

            $customerPayload = [
                'id'             => $targetCustomer->id,
                'name'           => $targetCustomer->name,
                'phone'          => $targetCustomer->phone,
                'cpf'            => $targetCustomer->cpf,
                'email'          => $targetCustomer->email,
                'birth_date'     => $targetCustomer->birth_date,
                'loyalty_points' => $targetCustomer->loyalty_points,
                'addresses'      => $targetCustomer->addresses->map(fn($a) => [
                    'id'              => $a->id,
                    'is_active'       => (bool) $a->is_active,
                    'nickname'        => $a->nickname,
                    'street'          => $a->street?->name ?? '',
                    'street_id'       => $a->street_id,
                    'neighborhood'    => $a->neighborhood?->name ?? '',
                    'neighborhood_id' => $a->neighborhood_id,
                    'number'          => $a->number ?? 'S/N',
                    'no_number'       => (bool) $a->no_number,
                    'complement'      => $a->complement,
                    'reference'       => $a->reference,
                    'full_address'    => trim(($a->street?->name ?? 'Rua') . ", " . ($a->number ?? 'S/N') . " - " . ($a->neighborhood?->name ?? 'Bairro')),
                    'delivery_fee'    => (float) ($a->delivery_fee ?? $a->neighborhood?->delivery_fee ?? 0)
                ])
            ];

            // 🚀 Busca configurações de Marketing para gerar a métrica virtual de Cashback
            $settings = CompanySetting::getCached();
            $isCashbackMode = ($settings->loyalty_reward_type ?? '') === 'cashback';
            $conversionRate = (float) ($settings->loyalty_cashback_conversion_rate ?? 5);

            // 🎯 LISTA BRANCA DO POS: apenas pedidos nestes status permanecem como ativos
            $posActiveStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'dispatched', 'pending_payment'];
            $tableMode = session('table_mode');

            if ($tableMode) {
                // 🚀 "Todos veem tudo": Busca os pedidos ativos DESTA MESA, não importa o cliente
                $myActiveOrders = Order::with([
                        'items.modifiers', 'items.subItems.modifiers', 'coupon:id,code,type,value',
                        'payments', 'payments.paymentMethod', 'customer:id,name'
                    ])
                    ->where('dining_table_id', $tableMode['id'])
                    ->whereIn('status', $posActiveStatuses)
                    ->orderBy('created_at', 'desc')->get()
                    ->map(function ($order) use ($isCashbackMode, $conversionRate) {
                        $order->cashback_earned = ($isCashbackMode && $order->loyalty_points_earned > 0)
                            ? round(($order->loyalty_points_earned / 100) * $conversionRate, 2)
                            : null;
                        return $order;
                    });
            } else {
                // 🚀 Fluxo normal (Delivery/Balcão/Pre-order)
                $myActiveOrders = Order::with(['items.modifiers', 'items.subItems.modifiers', 'coupon:id,code,type,value', 'payments', 'payments.paymentMethod'])
                    ->where('customer_id', $targetCustomer->id)
                    ->whereIn('status', $posActiveStatuses)
                    ->orderBy('created_at', 'desc')->get()
                    ->map(function ($order) use ($isCashbackMode, $conversionRate) {
                        $order->cashback_earned = ($isCashbackMode && $order->loyalty_points_earned > 0)
                            ? round(($order->loyalty_points_earned / 100) * $conversionRate, 2)
                            : null;
                        return $order;
                    });
            }

            // 🚀 Histórico V3 (cobre cancelamentos em ambas as grafias)
            $historyStatuses = ['closed', 'completed', 'delivered', 'finished', 'canceled', 'cancelled'];

            $myHistoryNew = Order::with(['items.modifiers', 'items.subItems.modifiers', 'coupon:id,code,type,value', 'payments', 'rating'])
                ->where('customer_id', $targetCustomer->id)
                ->whereIn('status', $historyStatuses)
                ->orderBy('created_at', 'desc')
                ->limit(15)->get()
                ->map(function ($order) use ($isCashbackMode, $conversionRate) {
                    $order->cashback_earned = ($isCashbackMode && $order->loyalty_points_earned > 0)
                        ? round(($order->loyalty_points_earned / 100) * $conversionRate, 2)
                        : null;
                    return $order;
                });

            // 🚀 OTIMIZAÇÃO: Busca prévia dos IDs legados sem OR EXISTS correlacionado
            $legacyCustomerIds = DB::table('legacy_customers')
                ->where('customer_id', $targetCustomer->id)
                ->pluck('id')
                ->all();

            $directOrderQuery = DB::table('legacy_orders')
                ->where('customer_id', $targetCustomer->id)
                ->select('id', 'issue_date');

            if (!empty($legacyCustomerIds)) {
                $viaLegacyCustomerQuery = DB::table('legacy_orders')
                    ->whereIn('legacy_customer_id', $legacyCustomerIds)
                    ->select('id', 'issue_date');

                $recentLegacyIds = $directOrderQuery->union($viaLegacyCustomerQuery)
                    ->orderBy('issue_date', 'desc')
                    ->limit(15)
                    ->pluck('id')
                    ->all();
            } else {
                $recentLegacyIds = $directOrderQuery
                    ->orderBy('issue_date', 'desc')
                    ->limit(15)
                    ->pluck('id')
                    ->all();
            }

            $myHistoryLegacy = empty($recentLegacyIds)
                ? collect()
                : LegacyOrder::with(['items.legacyProduct', 'items.modifiers', 'payments.legacyPaymentMethod'])
                    ->whereIn('id', $recentLegacyIds)
                    ->orderBy('issue_date', 'desc')
                    ->get()
                    ->map(function ($oldOrder) {
                        return [
                            'id' => 'LEG-' . $oldOrder->id,
                            'status' => 'closed',
                            'created_at' => $oldOrder->issue_date,
                            'total' => (float) $oldOrder->total_value,
                            'subtotal' => (float) ($oldOrder->total_value + $oldOrder->discount),
                            'delivery_fee' => 0,
                            'discount' => (float) $oldOrder->discount,
                            'coupon' => null,
                            'loyalty_points_earned' => 0,
                            'cashback_earned' => null,
                            'rating' => null,
                            'payments' => $oldOrder->payments->map(function($p) {
                                return ['method_name' => $p->legacyPaymentMethod?->name ?? 'Dinheiro'];
                            })->toArray(),
                            'items' => $oldOrder->items->map(function ($oldItem) {
                                return [
                                    'id' => 'leg-item-' . $oldItem->id,
                                    'product_id' => $oldItem->legacy_product_id,
                                    'product_name' => $oldItem->legacyProduct?->name ?? 'Item Antigo',
                                    'quantity' => (float) $oldItem->quantity,
                                    'unit_price' => (float) $oldItem->unit_price,
                                    'total_price' => (float) $oldItem->total_price,
                                    'status' => 'closed',
                                    'modifiers' => $oldItem->modifiers->map(function ($mod) {
                                        return [
                                            'name' => $mod->name ?? '',
                                            'quantity' => (float) ($mod->quantity ?? 1),
                                            'price' => (float) ($mod->price ?? 0),
                                        ];
                                    })->toArray(),
                                    'subItems' => [],
                                ];
                            })->toArray(),
                            'is_legacy' => true,
                        ];
                    });

            $myHistory = collect($myHistoryNew)->concat($myHistoryLegacy)->sortByDesc('created_at')->take(15)->values();
        }

        return [
            'customer' => $customerPayload,
            'isGuest' => $isGuest,
            'myActiveOrders' => $myActiveOrders,
            'myHistory' => $myHistory,
        ];
    }

    public function getDeliveryPaymentMethods()
    {
        return PaymentMethod::where('is_active', true)
            ->where('show_on_delivery', true)
            ->get();
    }

    public function getOrderForEditing(int $orderId): ?Order
    {
        return Order::with(['items.modifiers', 'items.subItems.modifiers'])->find($orderId);
    }

    public function getPopularProducts(): array
    {
        return Cache::store('file')->get('tenant_top_combinations', []);
    }
}
