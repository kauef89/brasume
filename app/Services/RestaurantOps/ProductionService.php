<?php

namespace App\Services\RestaurantOps;

use App\Models\ProductionOrder;
use App\Services\Catalog\InventoryService;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    /**
     * Finaliza a ordem de produção, dá entrada no item produzido (Kardex + WMS),
     * baixa os insumos da ficha técnica e notifica os terminais.
     */
    public function finishOrder(int $orderId, float $actualQty): void
    {
        DB::transaction(function () use ($orderId, $actualQty) {
            $order = ProductionOrder::with('product')->lockForUpdate()->findOrFail($orderId);

            // Trava de idempotência: evita duplicar entrada de produção se já estiver concluída
            if ($order->status === 'completed') {
                return;
            }

            $order->update([
                'status'     => 'completed',
                'actual_qty' => $actualQty,
            ]);

            $product = $order->product;

            if ($product && $actualQty > 0) {
                $inventoryService = app(InventoryService::class);

                // 1. Injeta o produto acabado no estoque (Kardex 'in' + WMS + restaura disponibilidade)
                if ($product->track_inventory) {
                    $inventoryService->addStock(
                        $product,
                        $actualQty,
                        "Ordem de Produção #{$order->id}",
                        $order
                    );
                }

                // 2. Baixa os insumos consumidos pela ficha técnica do item produzido
                $inventoryService->deductRecipeStock($product, $actualQty, $order);
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