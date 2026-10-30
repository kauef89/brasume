<?php

namespace App\Services\Orders;

use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Events\OrderUpdated;
use App\Services\Catalog\InventoryService;
use App\Services\RestaurantOps\KitchenDispatchService;
use Illuminate\Support\Facades\DB;

class PosCheckoutService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected KitchenDispatchService $kitchenDispatchService
    ) {}

    /**
     * Trava atômica que impede mutação financeira em pedidos já liquidados.
     */
    protected function assertOrderCanBeMutated(Order $order, bool $allowIfPaid = false): void
    {
        $isClosed = in_array($order->status, ['closed', 'cancelled', 'completed', 'merged', 'transferred'], true);
        if ($isClosed) {
            throw new \Exception("Ação Recusada: O Pedido #{$order->id} já se encontra finalizado, cancelado ou transferido.");
        }

        if (!$allowIfPaid && $order->payment_status === 'paid') {
            throw new \Exception("Ação Recusada: O Pedido #{$order->id} já se encontra quitado.");
        }
    }

    public function getCheckoutData(Order $order): array
    {
        $relations = ['items.modifiers', 'items.subItems.modifiers'];

        // 🚀 AQUI: Forçando a seleção dos campos de fidelidade do cliente!
        if (method_exists($order, 'customer')) {
            $relations['customer'] = fn($query) => $query->select('id', 'name', 'cpf', 'phone', 'loyalty_points', 'loyalty_points_expires_at');
        }

        if (method_exists($order, 'address')) $relations[] = 'address';
        if (method_exists($order, 'payments')) $relations[] = 'payments.items';
        if (method_exists($order, 'diningTable')) $relations[] = 'diningTable';

        $order->loadMissing($relations);

        $paymentMethods = PaymentMethod::where('is_active', true)
            ->where('show_on_pos', true)
            ->where('engine_type', '!=', 'internal_loyalty')
            ->orderBy('id', 'asc')
            ->get();

        $companySettings = CompanySetting::getCached() ?? Setting::first();

        if (!$companySettings) {
            $companySettings = (object) [
                'has_service_tax'     => true,
                'is_fiscal_emitter'   => false,
                'checkout_print_mode' => 'ask',
            ];
        }

        $hasFiscalDocument = \App\Models\FiscalDocument::where('order_id', $order->id)
            ->where('status', 'authorized')
            ->exists();

        return [
            'order'             => $order,
            'paymentMethods'    => $paymentMethods,
            'companySettings'   => $companySettings,
            'hasFiscalDocument' => $hasFiscalDocument,
        ];
    }

    /**
     * Duplica um item ou combo completo.
     * Subitens de combo são compulsoriamente convertidos em itens soltos ao preço de catálogo.
     */
    public function quickAdd(Order $order, int $itemId, bool $shouldPrint = false): OrderItem
    {
        $this->assertOrderCanBeMutated($order, true);

        $newItem = DB::transaction(function () use ($order, $itemId, $shouldPrint) {
            $originalItem = OrderItem::with(['modifiers', 'subItems.modifiers'])
                ->where('order_id', $order->id)
                ->findOrFail($itemId);

            $isSubItem = !empty($originalItem->parent_item_id);

            // =========================================================================
            // CENÁRIO A: É UM SUBITEM DE COMBO -> EMANCIPAÇÃO COMO ITEM SOLTO
            // =========================================================================
            if ($isSubItem) {
                $product = $originalItem->product_id ? Product::find($originalItem->product_id) : null;

                // 1. Resolução do preço real do produto fora do combo
                $isDelivery = in_array($order->type, ['delivery', 'takeaway', 'pickup'], true);
                $catalogPrice = 0.00;
                if ($product) {
                    $catalogPrice = ($isDelivery && ($product->delivery_price > 0))
                        ? (float) $product->delivery_price
                        : (float) ($product->dine_in_price ?? $product->price ?? 0);
                }

                // 2. Soma o valor dos adicionais/modificadores
                $origItemQty = max(1, (float) $originalItem->quantity);
                $modifiersExtra = 0.00;
                foreach ($originalItem->modifiers as $mod) {
                    $modQty = max(1, (int) round(($mod->quantity ?: 1) / $origItemQty));
                    $modifiersExtra += ((float) ($mod->price ?? 0)) * $modQty;
                }

                $finalUnitPrice = round($catalogPrice + $modifiersExtra, 2);

                // 3. Clona o item como SOLTO (parent_item_id = null)
                $clone = $originalItem->replicate([
                    'id',
                    'created_at',
                    'updated_at',
                    'fired_at',
                    'printed_at',
                    'paid_qty',
                ]);

                $clone->order_id          = $order->id;
                $clone->parent_item_id    = null; // 🛡️ Sem combo pai
                $clone->quantity          = 1;
                $clone->paid_qty          = 0.00;
                $clone->unit_price        = $finalUnitPrice;
                $clone->total_price       = $finalUnitPrice;
                $clone->status            = 'active';
                $clone->production_status = $shouldPrint ? 'pending' : 'ready';
                $clone->fired_at          = $shouldPrint ? null : now();
                $clone->save();

                // 4. Clona os modificadores para a nova linha independente
                foreach ($originalItem->modifiers as $modifier) {
                    $newMod = $modifier->replicate(['id', 'order_item_id', 'created_at', 'updated_at']);
                    $newMod->order_item_id = $clone->id;
                    $newMod->quantity      = max(1, (int) round(($modifier->quantity ?: 1) / $origItemQty));
                    $newMod->save();
                }

                if ($product) {
                    $this->inventoryService->deductStock($product, 1);
                }

                $this->syncOrderTotals($order);

                if ($shouldPrint) {
                    $this->kitchenDispatchService->dispatchNewItems($order);
                }

                return $clone;
            }

            // =========================================================================
            // CENÁRIO B: PRODUTO AVULSO OU COMBO PAI COMPLETO
            // =========================================================================
            $clone = $originalItem->replicate([
                'id',
                'created_at',
                'updated_at',
                'fired_at',
                'printed_at',
                'paid_qty',
            ]);

            $clone->order_id          = $order->id;
            $clone->parent_item_id    = null;
            $clone->quantity          = 1;
            $clone->paid_qty          = 0.00;
            $clone->unit_price        = (float) $originalItem->unit_price;
            $clone->total_price       = round(1 * (float) $originalItem->unit_price, 2);
            $clone->status            = 'active';
            $clone->production_status = $shouldPrint ? 'pending' : 'ready';
            $clone->fired_at          = $shouldPrint ? null : now();
            $clone->save();

            $origItemQty = max(1, (float) $originalItem->quantity);

            // Modificadores do item principal
            foreach ($originalItem->modifiers as $modifier) {
                $newMod = $modifier->replicate(['id', 'order_item_id', 'created_at', 'updated_at']);
                $newMod->order_item_id = $clone->id;
                $newMod->quantity      = max(1, (int) round(($modifier->quantity ?: 1) / $origItemQty));
                $newMod->save();
            }

            // Clona todos os subitens se for um combo inteiro
            foreach ($originalItem->subItems as $subItem) {
                $newSub = $subItem->replicate([
                    'id',
                    'parent_item_id',
                    'created_at',
                    'updated_at',
                    'fired_at',
                    'printed_at',
                    'paid_qty'
                ]);

                $subItemOrigQty = max(1, (float) $subItem->quantity);
                $newSubQty = max(1, (int) round($subItemOrigQty / $origItemQty));

                $newSub->parent_item_id    = $clone->id; // Vinculado ao novo combo pai
                $newSub->order_id          = $order->id;
                $newSub->quantity          = $newSubQty;
                $newSub->unit_price        = (float) $subItem->unit_price;
                $newSub->total_price       = round($newSubQty * (float) $subItem->unit_price, 2);
                $newSub->status            = 'active';
                $newSub->production_status = $shouldPrint ? 'pending' : 'ready';
                $newSub->fired_at          = $shouldPrint ? null : now();
                $newSub->paid_qty          = 0.00;
                $newSub->save();

                foreach ($subItem->modifiers as $subMod) {
                    $newSubMod = $subMod->replicate(['id', 'order_item_id', 'created_at', 'updated_at']);
                    $newSubMod->order_item_id = $newSub->id;
                    $newSubMod->quantity      = max(1, (int) round(($subMod->quantity ?: 1) / $subItemOrigQty));
                    $newSubMod->save();
                }

                if ($newSub->product_id) {
                    $subProduct = Product::find($newSub->product_id);
                    if ($subProduct) {
                        $this->inventoryService->deductStock($subProduct, (float) $newSub->quantity);
                    }
                }
            }

            if ($clone->product_id) {
                $product = Product::find($clone->product_id);
                if ($product) {
                    $this->inventoryService->deductStock($product, (float) $clone->quantity);
                }
            }

            $this->syncOrderTotals($order);

            if ($shouldPrint) {
                $this->kitchenDispatchService->dispatchNewItems($order);
            }

            return $clone;
        });

        broadcast(new OrderUpdated($order->fresh(['items', 'payments'])));

        return $newItem;
    }

    public function addItem(Order $order, array $data): OrderItem
    {
        $this->assertOrderCanBeMutated($order, true);

        return DB::transaction(function () use ($order, $data) {
            $product = Product::findOrFail($data['product_id']);
            $qty = (float) ($data['quantity'] ?? 1);
            $unitPrice = isset($data['unit_price'])
                ? (float) $data['unit_price']
                : (float) ($product->dine_in_price ?? $product->price ?? 0);

            $item = $order->items()->create([
                'product_id'  => $product->id,
                'name'        => $product->name,
                'quantity'    => $qty,
                'unit_price'  => $unitPrice,
                'total_price' => round($qty * $unitPrice, 2),
                'status'      => 'pending',
                'notes'       => $data['notes'] ?? null,
            ]);

            $this->syncOrderTotals($order);

            $this->inventoryService->processOrderItemStock($item, 'deduct');

            broadcast(new OrderUpdated($order->fresh(['items', 'payments'])));

            return $item;
        });
    }

    public function cancelItem(Order $order, int $itemId, string|float $qtyParam = 'all', ?string $reason = null): array
    {
        $this->assertOrderCanBeMutated($order);

        return DB::transaction(function () use ($order, $itemId, $qtyParam, $reason) {
            $targetItem = OrderItem::where('order_id', $order->id)->findOrFail($itemId);

            $qtyToCancel = (float) $targetItem->quantity;
            $isPartial = false;

            if ($qtyParam !== 'all' && (float) $qtyParam < (float) $targetItem->quantity) {
                $qtyToCancel = (float) $qtyParam;
                $isPartial = true;
            }

            if ($isPartial) {
                $targetItem->quantity -= $qtyToCancel;
                $targetItem->total_price = round($targetItem->quantity * $targetItem->unit_price, 2);
                $targetItem->save();

                $clone = $targetItem->replicate();
                $clone->quantity = $qtyToCancel;
                $clone->total_price = round($qtyToCancel * $clone->unit_price, 2);
                $clone->status = 'cancelled';
                $clone->cancel_reason = $reason ?? 'Cancelado no checkout (Parcial)';
                $clone->save();

                $this->inventoryService->processOrderItemStock($clone, 'restore');
            } else {
                $targetItem->update([
                    'status'        => 'cancelled',
                    'cancel_reason' => $reason ?? 'Cancelado no checkout',
                ]);

                $this->inventoryService->processOrderItemStock($targetItem, 'restore');
            }

            $this->syncOrderTotals($order);

            broadcast(new OrderUpdated($order->fresh(['items', 'payments'])));

            return [
                'is_partial'    => $isPartial,
                'cancelled_qty' => $qtyToCancel,
            ];
        });
    }

    public function applyDiscount(Order $order, float $discount): void
    {
        $this->assertOrderCanBeMutated($order);

        DB::transaction(function () use ($order, $discount) {
            $order->update(['discount' => $discount]);
            $this->syncOrderTotals($order);
        });

        broadcast(new OrderUpdated($order->fresh(['items', 'payments'])));
    }

    public function removeDiscount(Order $order): void
    {
        $this->assertOrderCanBeMutated($order);

        DB::transaction(function () use ($order) {
            $order->update(['discount' => 0]);
            $this->syncOrderTotals($order);
        });

        broadcast(new OrderUpdated($order->fresh(['items', 'payments'])));
    }

    public function toggleServiceFee(Order $order, bool $enabled, float $rate = 0.10): void
    {
        $this->assertOrderCanBeMutated($order);

        DB::transaction(function () use ($order, $enabled, $rate) {
            $subtotal = (float) $order->items()
                ->where('status', '!=', 'cancelled')
                ->sum('total_price');

            $serviceFee = $enabled ? round($subtotal * $rate, 2) : 0;

            $order->update(['service_fee' => $serviceFee]);
            $this->syncOrderTotals($order);
        });

        broadcast(new OrderUpdated($order->fresh(['items', 'payments'])));
    }

    public function linkCustomer(Order $order, ?int $customerId, ?string $customerName): void
    {
        $this->assertOrderCanBeMutated($order);

        $order->update([
            'customer_id'   => $customerId,
            'customer_name' => $customerName,
        ]);

        broadcast(new OrderUpdated($order->fresh(['items', 'payments'])));
    }

    public function syncOrderTotals(Order $order): void
    {
        if (method_exists($order, 'recalculateTotals')) {
            $order->recalculateTotals();
            return;
        }

        $subtotal = (float) $order->items()
            ->where('status', '!=', 'cancelled')
            ->sum('total_price');

        $deliveryFee = (float) ($order->delivery_fee ?? 0);
        $serviceFee  = (float) ($order->service_fee ?? 0);
        $discount    = (float) ($order->discount ?? 0);

        $total = max(0, round($subtotal + $deliveryFee + $serviceFee - $discount, 2));

        $order->update([
            'subtotal' => $subtotal,
            'total'    => $total,
        ]);

        if (method_exists($order, 'recalculatePaymentStatus')) {
            $order->recalculatePaymentStatus();
        }
    }
}
