<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\DiningTable;
use App\Models\CompanySetting;
use App\Events\OrderUpdated;
use App\Events\DiningTableUpdated;
use Illuminate\Support\Facades\DB;

class OrderTransferService
{
    public function execute(array $data): void
    {
        DB::transaction(function () use ($data) {
            $sourceOrder = Order::lockForUpdate()->findOrFail($data['source_order_id']);
            $itemIds = $data['item_ids'];
            $transferQuantities = $data['item_quantities'] ?? [];

            // 1. Localização e validação dos itens principais
            $mainItems = OrderItem::where('order_id', $sourceOrder->id)
                ->whereIn('id', $itemIds)
                ->lockForUpdate()
                ->get();

            if ($mainItems->isEmpty()) {
                throw new \Exception("Nenhum item válido encontrado para transferência.");
            }

            foreach ($mainItems as $item) {
                if ($item->paid_qty > 0) {
                    throw new \Exception("Não é possível transferir '{$item->product_name}' pois o item já possui pagamentos vinculados.");
                }

                if ($item->parent_item_id && !in_array($item->parent_item_id, $itemIds)) {
                    throw new \Exception("Não é possível transferir o adicional/subitem '{$item->product_name}' sem transferir o item principal.");
                }
            }

            // 2. Destino do Pedido
            $destOrder = $this->resolveDestinationOrder($data, $sourceOrder);

            if ($sourceOrder->id === $destOrder->id) {
                throw new \Exception("A comanda de destino não pode ser a mesma de origem.");
            }

            // 3. Processamento dos Itens (com suporte a Fracionamento/Split de Quantidade)
            foreach ($mainItems as $item) {
                $requestedQty = isset($transferQuantities[$item->id]) ? (float) $transferQuantities[$item->id] : (float) $item->quantity;
                $currentQty = (float) $item->quantity;

                // Captura subitens (combos)
                $childItems = OrderItem::where('order_id', $sourceOrder->id)
                    ->where('parent_item_id', $item->id)
                    ->lockForUpdate()
                    ->get();

                // Transferência Integral
                if ($requestedQty >= $currentQty || $requestedQty <= 0) {
                    $item->update(['order_id' => $destOrder->id]);
                    foreach ($childItems as $child) {
                        $child->update(['order_id' => $destOrder->id]);
                    }
                }
                // Transferência Parcial (Split)
                else {
                    $remainingQty = $currentQty - $requestedQty;
                    $item->update([
                        'quantity'    => $remainingQty,
                        'total_price' => round($remainingQty * (float) $item->unit_price, 2)
                    ]);

                    // Clona o item com a quantidade fracionada para o destino
                    $newItem = $item->replicate(['id', 'created_at', 'updated_at']);
                    $newItem->order_id = $destOrder->id;
                    $newItem->quantity = $requestedQty;
                    $newItem->total_price = round($requestedQty * (float) $item->unit_price, 2);
                    $newItem->save();

                    // Replica modificadores vinculados
                    foreach ($item->modifiers as $mod) {
                        $newMod = $mod->replicate(['id', 'order_item_id']);
                        $newMod->order_item_id = $newItem->id;
                        $newMod->quantity = round(((float)$mod->quantity / $currentQty) * $requestedQty, 4);
                        $newMod->save();
                    }

                    // Fraciona e transfere os subitens
                    foreach ($childItems as $child) {
                        $childQty = (float) $child->quantity;
                        $childUnitRatio = $currentQty > 0 ? ($childQty / $currentQty) : 1;
                        $newChildQty = round($childUnitRatio * $requestedQty, 4);
                        $remChildQty = round($childQty - $newChildQty, 4);

                        $child->update(['quantity' => $remChildQty]);

                        $newChild = $child->replicate(['id', 'created_at', 'updated_at']);
                        $newChild->order_id = $destOrder->id;
                        $newChild->parent_item_id = $newItem->id;
                        $newChild->quantity = $newChildQty;
                        $newChild->save();

                        foreach ($child->modifiers as $cMod) {
                            $newCMod = $cMod->replicate(['id', 'order_item_id']);
                            $newCMod->order_item_id = $newChild->id;
                            $newCMod->save();
                        }
                    }
                }
            }

            // 4. Recalcula os totais de Origem
            $this->recalculateOrderTotals($sourceOrder);

            $remainingActiveItems = $sourceOrder->items()
                ->where('status', '!=', 'cancelled')
                ->count();

            if ($remainingActiveItems === 0) {
                $sourceOrder->update([
                    'status' => 'transferred',
                    'notes'  => trim(($sourceOrder->notes ? $sourceOrder->notes . ' | ' : '') . "Itens transferidos para {$destOrder->customer_name} (#{$destOrder->id})"),
                ]);

                $this->freeTableIfEmpty($sourceOrder);
            }

            // 5. Recalcula os totais de Destino
            $this->recalculateOrderTotals($destOrder, $sourceOrder);
            $this->occupyDestinationTable($destOrder);

            // 6. Notificação WebSockets
            try {
                broadcast(new OrderUpdated($sourceOrder->fresh(['items', 'payments'])));
                broadcast(new OrderUpdated($destOrder->fresh(['items', 'payments'])));
            } catch (\Throwable $e) {}
        });
    }

    private function recalculateOrderTotals(Order $order, ?Order $sourceOrderForTax = null): void
    {
        $subtotal = (float) $order->items()
            ->where('status', '!=', 'cancelled')
            ->whereNull('parent_item_id')
            ->sum('total_price');

        $deliveryFee = (float) ($order->delivery_fee ?? 0);
        $discount    = (float) ($order->discount ?? 0);

        $serviceFee = 0.00;
        $setting = CompanySetting::getCached() ?? CompanySetting::first();
        $hasServiceTax = (bool) ($setting?->has_service_tax ?? false);
        $serviceTaxTable = (bool) ($setting?->service_tax_table ?? true);

        if ($hasServiceTax && $order->type === 'table' && $serviceTaxTable && $subtotal > 0) {
            $taxRate = ((float) ($setting?->service_tax_percentage ?? 10.0)) / 100;
            $serviceFee = round($subtotal * $taxRate, 2);
        } elseif ((float) $order->service_fee > 0 && $subtotal > 0) {
            $oldSubtotal = (float) $order->subtotal > 0 ? (float) $order->subtotal : $subtotal;
            $rate = (float) $order->service_fee / $oldSubtotal;
            $serviceFee = round($subtotal * $rate, 2);
        }

        $total = max(0, round($subtotal + $deliveryFee + $serviceFee - $discount, 2));

        $order->update([
            'subtotal'    => $subtotal,
            'service_fee' => $serviceFee,
            'total'       => $total,
        ]);

        if (method_exists($order, 'recalculatePaymentStatus')) {
            $order->recalculatePaymentStatus();
        }
    }

    private function resolveDestinationOrder(array $data, Order $sourceOrder): Order
    {
        if ($data['destination_type'] === 'table') {
            $intent = $data['destination_order_intent'] ?? null;

            if (is_numeric($intent)) {
                $destOrder = Order::lockForUpdate()->findOrFail((int) $intent);

                if (in_array($destOrder->status, ['closed', 'cancelled', 'merged', 'completed', 'transferred'])) {
                    throw new \Exception("A comanda de destino (#{$destOrder->id}) já está finalizada ou transferida.");
                }

                return $destOrder;
            }

            $tableId = $data['destination_dining_table_id'] ?? ($data['destination_table_id'] ?? null);
            $tableName = $data['destination_table_name'] ?? null;

            $table = null;
            if ($tableId) {
                $table = DiningTable::find($tableId);
            } elseif ($tableName) {
                $table = DiningTable::where('name', $tableName)->first();
            }

            if (!$table) {
                throw new \Exception("Mesa de destino não localizada para concluir a transferência.");
            }

            $tableId = $table->id;
            $tableName = $table->name;

            if ($intent !== 'new_subaccount') {
                $existingActiveOrder = Order::where(function ($q) use ($table) {
                        $q->where('dining_table_id', $table->id)
                          ->orWhere('table_number', $table->name);
                    })
                    ->where('id', '!=', $sourceOrder->id)
                    ->whereNotIn('status', ['closed', 'cancelled', 'merged', 'completed', 'transferred'])
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if ($existingActiveOrder) {
                    return $existingActiveOrder;
                }
            }

            $customerName = ($intent === 'new_subaccount' && !empty($data['new_subaccount_name']))
                ? trim($data['new_subaccount_name'])
                : "Mesa " . $tableName;

            return Order::create([
                "cash_shift_id"   => $sourceOrder->cash_shift_id,
                "dining_table_id" => (int) $tableId,
                "table_number"    => $tableName,
                "customer_name"   => $customerName,
                "type"            => "table",
                "status"          => "pending",
                "payment_status"  => "pending",
                "subtotal"        => 0,
                "discount"        => 0,
                "delivery_fee"    => 0,
                "service_fee"     => 0,
                "total"           => 0,
                "employee_id"     => auth()->id() ?? $sourceOrder->employee_id,
            ]);
        }

        $type = ($data['destination_order_intent'] ?? '') === 'new_delivery' ? 'delivery' : 'counter';

        return Order::create([
            "cash_shift_id"  => $sourceOrder->cash_shift_id,
            "customer_name"  => "Novo " . ucfirst($type),
            "type"           => $type,
            "status"         => "pending",
            "payment_status" => "pending",
            "subtotal"       => 0,
            "discount"       => 0,
            "delivery_fee"   => 0,
            "service_fee"    => 0,
            "total"          => 0,
            "employee_id"    => auth()->id() ?? $sourceOrder->employee_id,
        ]);
    }

    private function freeTableIfEmpty(Order $sourceOrder): void
    {
        $table = $sourceOrder->diningTable
            ?? ($sourceOrder->dining_table_id ? DiningTable::find($sourceOrder->dining_table_id) : null)
            ?? (!empty($sourceOrder->table_number) ? DiningTable::where('name', $sourceOrder->table_number)->first() : null);

        if (!$table) return;

        // Se existirem outros pedidos ativos no grupo de mesas unidas, não altera
        if ($table->hasActiveOrdersInGroup($sourceOrder->id)) {
            return;
        }

        // Se o grupo inteiro estiver sem pedidos, liberta todas as mesas do grupo
        $groupTableIds = $table->getGroupTableIds();
        DiningTable::whereIn('id', $groupTableIds)->update(['status' => 'available']);

        DB::afterCommit(function () use ($groupTableIds) {
            foreach ($groupTableIds as $id) {
                $t = DiningTable::find($id);
                if ($t) broadcast(new DiningTableUpdated($t));
            }
        });
    }

    private function occupyDestinationTable(Order $destOrder): void
    {
        if ($destOrder->type !== 'table') return;

        $table = $destOrder->diningTable
            ?? ($destOrder->dining_table_id ? DiningTable::find($destOrder->dining_table_id) : null)
            ?? (!empty($destOrder->table_number) ? DiningTable::where('name', $destOrder->table_number)->first() : null);

        if (!$table) return;

        // Ocupa a mesa e todas as mesas que estiverem agrupadas com ela
        $groupTableIds = $table->getGroupTableIds();
        DiningTable::whereIn('id', $groupTableIds)->where('status', '!=', 'occupied')->update(['status' => 'occupied']);

        DB::afterCommit(function () use ($groupTableIds) {
            foreach ($groupTableIds as $id) {
                $t = DiningTable::find($id);
                if ($t) broadcast(new DiningTableUpdated($t));
            }
        });
    }
}
