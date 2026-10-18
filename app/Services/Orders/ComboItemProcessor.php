<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ComboGroup;
use App\Models\ComboItem;
use App\Models\ModifierOption;
use App\Models\PosAudit;
use App\Services\Catalog\InventoryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ComboItemProcessor
{
    private static array $productCache = [];
    private static array $modifierOptionCache = [];

    public function __construct(protected InventoryService $inventoryService) {}

    protected function getProduct(int $id): ?Product
    {
        if (!isset(self::$productCache[$id])) {
            self::$productCache[$id] = Product::find($id);
        }
        return self::$productCache[$id];
    }

    protected function getModifierOption(int $id): ?ModifierOption
    {
        if (!isset(self::$modifierOptionCache[$id])) {
            self::$modifierOptionCache[$id] = ModifierOption::find($id);
        }
        return self::$modifierOptionCache[$id];
    }

    /**
     * Instancia e vincula todos os subitens de um combo novo.
     */
    public function processChildren(Order $order, OrderItem $parentItem, array $comboItems, float $parentQty): Collection
    {
        $createdChildren = collect();

        foreach ($comboItems as $cItem) {
            $childProductId = $cItem["product_id"] ?? $cItem["id"] ?? null;
            if (is_string($childProductId) && preg_match('/_prod_(\d+)/', $childProductId, $matches)) {
                $childProductId = (int) $matches[1];
            }

            $childProductModel = $childProductId ? $this->getProduct((int)$childProductId) : null;

            $subQty = (float) ($cItem["qty"] ?? 1);
            $subPrice = (float) ($cItem["price"] ?? 0);
            $subNotes = !empty($cItem["notes"]) ? trim((string) $cItem["notes"]) : null;

            // Herda a flag de viagem do combo pai caso não venha explícita no subitem
            $isTakeaway = (bool) ($parentItem->is_takeaway || !empty($cItem["isTakeaway"]) || !empty($cItem["is_takeaway"]));

            $subOrderItem = $order->items()->create([
                "parent_item_id"    => $parentItem->id,
                "is_combo_item"     => true,
                "product_id"        => $childProductId,
                "product_name"      => $cItem["name"] ?? 'Item de Combo',
                "quantity"          => $subQty * $parentQty,
                "unit_price"        => $subPrice,
                "total_price"       => $subPrice * ($subQty * $parentQty),
                "kitchen_id"        => $childProductModel?->kitchen_id,
                "prep_time_minutes" => $childProductModel?->prep_time_minutes,
                "notes"             => $subNotes,
                "user_name"         => auth()->user()?->name ?? 'Sistema',
                "is_takeaway"       => $isTakeaway,
                "status"            => 'active',
                "production_status" => 'pending',
            ]);

            if ($childProductModel) {
                $this->inventoryService->deductStock($childProductModel, $subQty * $parentQty);
            }

            if (!empty($cItem["structuredModifiers"]) && is_array($cItem["structuredModifiers"])) {
                $this->attachModifiers($subOrderItem, $cItem["structuredModifiers"], $subQty * $parentQty);
            }

            $createdChildren->push($subOrderItem);
        }

        return $createdChildren;
    }

    /**
     * Processa a substituição/edição de um único subitem dentro de um combo existente.
     */
    public function processChildReplacement(Order $order, array $itemData, OrderItem $oldChild): OrderItem
    {
        $parentCombo = OrderItem::find($oldChild->parent_item_id);
        $oldChildPrice = (float) $oldChild->total_price;

        // 1. Estorna estoque e modificadores do subitem antigo
        $oldProduct = $this->getProduct($oldChild->product_id);
        if ($oldProduct) {
            $this->inventoryService->deductStock($oldProduct, -$oldChild->quantity);
        }

        foreach ($oldChild->modifiers as $mod) {
            if (!empty($mod->modifier_option_id)) {
                $modifierOption = $this->getModifierOption($mod->modifier_option_id);
                if ($modifierOption && $modifierOption->linked_product_id) {
                    $linkedProduct = $this->getProduct($modifierOption->linked_product_id);
                    if ($linkedProduct) {
                        $deduction = ((float) ($modifierOption->linked_qty ?? 1)) * ((float) ($mod->quantity ?? 1));
                        $this->inventoryService->deductStock($linkedProduct, -$deduction);
                    }
                }
            }
        }

        if (!empty($itemData['is_loss']) && $oldProduct) {
            $this->inventoryService->registerLoss(
                $oldProduct,
                $oldChild->quantity,
                'Modificação de Combo: ' . ($itemData["auth_reason"] ?? 'Alteração de ingredientes'),
                'Lançamento gerado na substituição de subitem de combo.',
                auth()->id(),
                $oldChild->id
            );
        }

        $oldChild->update([
            'status'            => 'cancelled',
            'production_status' => 'cancelled',
            'cancel_reason'     => 'Substituído por alteração de ingredientes'
        ]);

        // 2. Calcula novo valor
        $productId = $itemData["id"] ?? $oldChild->product_id;
        $productModel = $productId ? $this->getProduct((int)$productId) : null;
        $productName = $itemData["name"] ?? $oldChild->product_name;

        $newExtraPrice = (float) ($itemData["extra_price"] ?? $itemData["extraPrice"] ?? 0);
        $qty = (float) ($itemData["qty"] ?? $oldChild->quantity);
        $newTotalPrice = round($newExtraPrice * $qty, 2);

        $notes = !empty($itemData["notes"]) ? trim((string) $itemData["notes"]) : null;
        $notes = trim(($notes ? $notes . " " : "") . "*** MODIFICAÇÃO DE ITEM DO COMBO ***");

        $isTakeaway = (bool) ($parentCombo?->is_takeaway || $oldChild->is_takeaway || !empty($itemData["isTakeaway"]) || !empty($itemData["is_takeaway"]));

        // 3. Cria o novo subitem com parent_item_id e flag is_takeaway preservados
        $newChild = $order->items()->create([
            "parent_item_id"    => $oldChild->parent_item_id,
            "is_combo_item"     => true,
            "product_id"        => $productId,
            "product_name"      => $productName,
            "quantity"          => $qty,
            "unit_price"        => $newExtraPrice,
            "total_price"       => $newTotalPrice,
            "kitchen_id"        => $productModel?->kitchen_id ?? $oldChild->kitchen_id,
            "prep_time_minutes" => $productModel?->prep_time_minutes ?? $oldChild->prep_time_minutes,
            "notes"             => $notes,
            "user_name"         => auth()->user()?->name ?? 'Sistema',
            "is_takeaway"       => $isTakeaway,
            "status"            => 'active',
            "production_status" => 'pending',
        ]);

        if ($productModel) {
            $this->inventoryService->deductStock($productModel, $qty);
        }

        if (!empty($itemData["structuredModifiers"]) && is_array($itemData["structuredModifiers"])) {
            $this->attachModifiers($newChild, $itemData["structuredModifiers"], $qty);
        }

        // 4. Ajuste da diferença de preço na conta e no combo pai
        $priceDelta = round($newTotalPrice - $oldChildPrice, 2);

        if ($priceDelta != 0) {
            if ($parentCombo) {
                $parentCombo->increment('total_price', $priceDelta);
            }
            $order->increment('subtotal', $priceDelta);
            $order->increment('total', $priceDelta);
        }

        PosAudit::create([
            'action'        => 'edit_combo_child',
            'operator_id'   => auth()->id(),
            'authorizer_id' => auth()->id(),
            'reason'        => $itemData["auth_reason"] ?? 'Edição de item de combo',
            'context'       => [
                'parent_combo_id' => $oldChild->parent_item_id,
                'old_item_id'     => $oldChild->id,
                'new_item_id'     => $newChild->id,
                'price_delta'     => $priceDelta,
            ]
        ]);

        Log::info("🍔 [ComboItemProcessor] Subitem #{$oldChild->id} substituído por #{$newChild->id} no Combo #{$oldChild->parent_item_id} (Delta: R$ {$priceDelta}).");

        return $newChild;
    }

    public function attachModifiers(OrderItem $orderItem, array $structuredModifiers, float $qtyMultiplier): void
    {
        foreach ($structuredModifiers as $mod) {
            if (empty($mod["name"])) continue;

            $modQty = (float) ($mod["qty"] ?? $mod["quantity"] ?? 1);
            $optionId = !empty($mod["id"]) ? (int) $mod["id"] : null;
            $groupId = !empty($mod["group_id"]) ? (int) $mod["group_id"] : null;

            $orderItem->modifiers()->create([
                "modifier_option_id" => $optionId,
                "modifier_group_id"  => $groupId,
                "name"                => trim((string) $mod["name"]),
                "quantity"            => $modQty * $qtyMultiplier,
                "price"               => (float) ($mod["price"] ?? 0),
            ]);

            if ($optionId) {
                $modifierOption = $this->getModifierOption($optionId);
                if ($modifierOption && $modifierOption->linked_product_id) {
                    $linkedProduct = $this->getProduct($modifierOption->linked_product_id);
                    if ($linkedProduct) {
                        $deduction = ((float) ($modifierOption->linked_qty ?? 1)) * ($modQty * $qtyMultiplier);
                        $this->inventoryService->deductStock($linkedProduct, $deduction);
                    }
                }
            }
        }
    }
}
