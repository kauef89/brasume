<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Customer;
use App\Services\Marketing\LoyaltyService;

class OrderObserver
{
    /**
     * Disparado sempre que um Pedido sofre um UPDATE no banco.
     */
    public function updated(Order $order): void
    {
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
