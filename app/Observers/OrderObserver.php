<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Order;
use App\Services\Catalog\InventoryService;
use App\Services\Marketing\LoyaltyService;
use App\Services\Orders\CourierSettlementService;

class OrderObserver
{
    /**
     * Disparado sempre que um Pedido é CRIADO no banco.
     */
    public function created(Order $order): void
    {
        if ($order->isDelivery() && empty($order->courier_id)) {
            app(CourierSettlementService::class)->tryAutoAssign($order);
        }
    }

    /**
     * Disparado sempre que um Pedido sofre um UPDATE no banco.
     */
    public function updated(Order $order): void
    {
        if ($order->isDelivery() && empty($order->courier_id) && in_array($order->status, ['ready_for_pickup', 'dispatched'])) {
            app(CourierSettlementService::class)->tryAutoAssign($order);
        }

        // 🚀 CENÁRIO 1: O pedido foi FINALIZADO
        if ($order->wasChanged('status') && in_array($order->status, ['closed', 'completed', 'delivered', 'finished'])) {
            if ($order->customer_id && (int) ($order->loyalty_points_earned ?? 0) === 0) {
                $customer = $order->customer ?: Customer::find($order->customer_id);
                if ($customer) {
                    app(LoyaltyService::class)->earnPoints($order, $customer);
                }
            }
        }

        // 🚀 CENÁRIO 2: O pedido foi CANCELADO (Garante reposição física e Kardex)
        if ($order->wasChanged('status') && $order->status === 'cancelled') {
            $order->loadMissing(['items.modifiers', 'items.subItems.modifiers']);
            $inventoryService = app(InventoryService::class);

            foreach ($order->items as $item) {
                // Apenas repõe artigos que ainda não tinham sido estornados isoladamente
                if ($item->status !== 'cancelled' && $item->parent_item_id === null) {
                    $inventoryService->processOrderItemStock($item, 'restore');

                    $item->updateQuietly([
                        'status'            => 'cancelled',
                        'production_status' => 'cancelled',
                        'cancel_reason'     => 'Cancelamento do Pedido #' . $order->id,
                    ]);
                }
            }

            if ($order->customer_id) {
                app(LoyaltyService::class)->refundOrderCashback($order);
            }
        }
    }
}
