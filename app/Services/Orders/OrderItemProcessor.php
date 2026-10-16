<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PosAudit;
use App\Models\ModifierOption;
use App\Services\Catalog\InventoryService;

class OrderItemProcessor
{
    private static array $productCache = [];
    private static array $modifierOptionCache = [];

    public function __construct(
        protected InventoryService $inventoryService,
        protected ComboItemProcessor $comboProcessor
    ) {}

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

    public function process(Order $order, array $itemData): OrderItem
    {
        $isReplacement = !empty($itemData["replaces_item_id"]);

        // 🛡️ ENCAMINHAMENTO CIRÚRGICO: Se estiver substituindo um subitem de combo
        if ($isReplacement) {
            $oldItem = OrderItem::with(['modifiers', 'subItems.modifiers'])->find($itemData["replaces_item_id"]);
            if ($oldItem && !empty($oldItem->parent_item_id)) {
                return $this->comboProcessor->processChildReplacement($order, $itemData, $oldItem);
            }

            if ($oldItem) {
                $this->handleRootReplacement($order, $itemData, $oldItem);
            }
        }

        $productId = $itemData["id"] ?? null;
        $productModel = $productId ? $this->getProduct((int)$productId) : null;
        $productName = $itemData["name"] ?? 'Produto';
        if (!empty($itemData["isTakeaway"])) {
            $productName .= " (Viagem)";
        }

        $notes = !empty($itemData["notes"]) ? trim((string) $itemData["notes"]) : null;
        if ($isReplacement) {
            $notes = trim(($notes ? $notes . " " : "") . "*** ATENÇÃO: ITEM MODIFICADO NO SISTEMA ***");
        }

        $hasComboItems = !empty($itemData["comboItems"]) && is_array($itemData["comboItems"]);
        $qty = (float) ($itemData["qty"] ?? 1);
        $unitPrice = (float) ($itemData["price"] ?? 0);

        $mainOrderItem = $order->items()->create([
            "parent_item_id"    => null,
            "product_id"        => $productId,
            "product_name"      => $productName,
            "quantity"          => $qty,
            "unit_price"        => $unitPrice,
            "total_price"       => round($unitPrice * $qty, 2),
            "kitchen_id"        => $productModel?->kitchen_id,
            "prep_time_minutes" => $productModel?->prep_time_minutes,
            "notes"             => $notes,
            "user_name"         => auth()->user()?->name ?? 'Sistema',
            "is_combo_item"     => $hasComboItems,
            "status"            => 'active',
            "production_status" => 'pending',
        ]);

        if ($productModel) {
            $this->inventoryService->deductStock($productModel, $qty);
        }

        // 🚀 Delega com segurança ao ComboItemProcessor nativo
        if (!empty($itemData["structuredModifiers"]) && is_array($itemData["structuredModifiers"])) {
            $this->comboProcessor->attachModifiers($mainOrderItem, $itemData["structuredModifiers"], $qty);
        }

        if ($hasComboItems) {
            $this->comboProcessor->processChildren($order, $mainOrderItem, $itemData["comboItems"], $qty);
        }

        return $mainOrderItem;
    }

    private function handleRootReplacement(Order $order, array $itemData, OrderItem $oldItem): void
    {
        if ($oldItem->status === 'cancelled') return;

        if ((float) $oldItem->total_price > 0) {
            $order->decrement('subtotal', $oldItem->total_price);
            $order->decrement('total', $oldItem->total_price);
        }

        $oldProduct = $this->getProduct($oldItem->product_id);
        if ($oldProduct) {
            $this->inventoryService->deductStock($oldProduct, -$oldItem->quantity);
        }

        foreach ($oldItem->modifiers as $mod) {
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
                $oldItem->quantity,
                'Substituição de Pedido: ' . ($itemData["auth_reason"] ?? 'Não especificado'),
                'Lançamento automático de cancelamento de item pai.',
                auth()->id(),
                $oldItem->id
            );
        }

        $oldItem->update(['status' => 'cancelled', 'production_status' => 'cancelled']);

        OrderItem::where('parent_item_id', $oldItem->id)->update([
            'status'            => 'cancelled',
            'production_status' => 'cancelled'
        ]);

        PosAudit::create([
            'action'        => 'edit_item',
            'operator_id'   => auth()->id(),
            'authorizer_id' => auth()->id(),
            'reason'        => $itemData["auth_reason"] ?? 'Edição de Item',
            'context'       => ['old_item_id' => $oldItem->id, 'new_product' => $itemData["name"], 'is_loss' => $itemData["is_loss"] ?? false]
        ]);
    }
}
