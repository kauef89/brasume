<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Order;
use App\Services\Marketing\LoyaltyService;
use App\Services\Orders\CourierSettlementService;

class OrderObserver
{
    /**
     * Disparado sempre que um Pedido é CRIADO no banco.
     */
    public function created(Order $order): void
    {
        // 🚀 Autoatribui o entregador se houver apenas 1 motoboy logado no turno atual
        if ($order->isDelivery() && empty($order->courier_id)) {
            app(CourierSettlementService::class)->tryAutoAssign($order);
        }
    }

    /**
     * Disparado sempre que um Pedido sofre um UPDATE no banco.
     */
    public function updated(Order $order): void
    {
        // 🚀 Tenta autoatribuir se o pedido virar entrega ou avançar para despacho sem motoboy
        if ($order->isDelivery() && empty($order->courier_id) && in_array($order->status, ['ready_for_pickup', 'dispatched'])) {
            app(CourierSettlementService::class)->tryAutoAssign($order);
        }

        // 🚀 CENÁRIO 1: O pedido foi FINALIZADO (cobre balcão, mesa e entregas)
        if ($order->wasChanged('status') && in_array($order->status, ['closed', 'completed', 'delivered', 'finished'])) {
            if ($order->customer_id && (int) ($order->loyalty_points_earned ?? 0) === 0) {
                $customer = $order->customer ?: Customer::find($order->customer_id);
                if ($customer) {
                    app(LoyaltyService::class)->earnPoints($order, $customer);
                }
            }
        }

        // 🚀 CENÁRIO 2: O pedido foi CANCELADO
        if ($order->wasChanged('status') && $order->status === 'cancelled') {
            if ($order->customer_id) {
                app(LoyaltyService::class)->refundOrderCashback($order);
            }
        }
    }
}
