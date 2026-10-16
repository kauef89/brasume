<?php

namespace App\Services\RestaurantOps;

use App\Models\ProductionOrder;
use App\Events\CatalogUpdated;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    /**
     * Finaliza a ordem de produção, injeta no estoque e notifica os terminais.
     */
    public function finishOrder(int $orderId, float $actualQty): void
    {
        DB::transaction(function () use ($orderId, $actualQty) {
            $order = ProductionOrder::with('product')->findOrFail($orderId);

            $order->update([
                'status'     => 'completed',
                'actual_qty' => $actualQty
            ]);

            $product = $order->product;

            if ($product && $product->track_inventory) {
                $product->increment('current_stock', $actualQty);

                if ($product->status === 'out_of_stock' && $product->current_stock > 0) {
                    $product->update(['status' => 'available']);

                    $tenant = function_exists('tenant') && tenant('id') ? tenant('id') : 'central';
                    broadcast(new CatalogUpdated($tenant));
                }
            }
        });
    }

    public function updateStatus(int $orderId, string $status): void
    {
        $order = ProductionOrder::findOrFail($orderId);
        $order->update(['status' => $status]);
    }
}

// 🚀 ALIAS DE COMPATIBILIDADE
class_alias(ProductionService::class, 'App\Services\Operations\ProductionService');
