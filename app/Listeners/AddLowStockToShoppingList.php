<?php

namespace App\Listeners;

use App\Events\StockLowDetected;
use App\Models\Tenant;
use App\Models\ShoppingListItem;
use App\Models\ProductCostHistory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AddLowStockToShoppingList implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(StockLowDetected $event): void
    {
        if ($event->tenantId !== 'central') {
            $tenant = Tenant::find($event->tenantId);
            if ($tenant) tenancy()->initialize($tenant);
        }

        $product = $event->product;

        // 1. Calcula a meta de reposição (Prioridade: max_stock -> par_level -> min_stock * 2 -> 1)
        $targetStock = (float) ($product->max_stock ?? ($product->par_level ?? (($product->min_stock ?? 0) * 2)));
        $currentStock = (float) ($product->current_stock ?? 0);
        $deficit = max(1.0, round($targetStock - $currentStock, 3));

        // 2. Busca registro pendente existente ou instancia novo
        $item = ShoppingListItem::firstOrNew([
            'product_id' => $product->id,
            'status'     => 'pending'
        ]);

        // 3. Atualiza com o déficit necessário (sem inflação por vendas repetidas)
        $item->suggested_qty = $item->exists ? max((float) $item->suggested_qty, $deficit) : $deficit;

        // Se o evento for crítico (ruptura/zerado), eleva a urgência
        if ($event->urgency === 'critical' || $currentStock <= 0) {
            $item->urgency_level = 'critical';
        } elseif (!$item->exists) {
            $item->urgency_level = $event->urgency ?? 'normal';
        }

        // 4. Resolve fornecedor preferencial se ainda não estiver preenchido
        if (!$item->supplier_id) {
            $item->supplier_id = $product->supplier_id
                ?? ProductCostHistory::where('product_id', $product->id)->latest('id')->value('supplier_id');
        }

        // 5. Custo unitário de referência
        $unitCost = (float) ($product->base_cost ?? 0);
        if ($unitCost > 0) {
            $item->estimated_cost = round($item->suggested_qty * $unitCost, 2);
        }

        $item->save();

        if ($event->tenantId !== 'central') tenancy()->end();
    }
}
