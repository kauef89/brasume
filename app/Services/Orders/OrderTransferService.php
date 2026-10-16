<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\DiningTable;
use App\Events\OrderUpdated;
use App\Events\DiningTableUpdated;
use App\Services\RestaurantOps\TablesService;
use Illuminate\Support\Facades\DB;

class OrderTransferService
{
    public function execute(array $data): void
    {
        DB::transaction(function () use ($data) {
            $sourceOrder = Order::findOrFail($data['source_order_id']);
            $itemIds = $data['item_ids'];

            $mainItems = OrderItem::where('order_id', $sourceOrder->id)
                ->whereIn('id', $itemIds)
                ->get();

            if ($mainItems->isEmpty()) {
                throw new \Exception("Nenhum item válido encontrado para transferência.");
            }

            foreach ($mainItems as $item) {
                if ($item->paid_qty > 0) {
                    throw new \Exception("Não é possível transferir '{$item->product_name}' pois o item já possui pagamentos vinculados.");
                }
            }

            $childItems = OrderItem::where('order_id', $sourceOrder->id)
                ->whereIn('parent_item_id', $mainItems->pluck('id'))
                ->get();

            $transferValue = (float) $mainItems->sum('total_price');

            // 1. Resolve ou localiza a comanda de destino
            $destOrder = $this->resolveDestinationOrder($data);

            // 2. Transfere fisicamente os itens para o pedido de destino
            $allItemsToTransfer = $mainItems->pluck('id')->merge($childItems->pluck('id'));
            OrderItem::whereIn('id', $allItemsToTransfer)->update(['order_id' => $destOrder->id]);

            // 3. Recalcula a comanda de origem
            $this->recalculateOrderTotals($sourceOrder);

            // Verifica se ainda restam itens ativos na comanda de origem
            $remainingActiveItems = $sourceOrder->items()
                ->where('status', '!=', 'cancelled')
                ->count();

            if ($remainingActiveItems === 0) {
                // 🚀 Em vez de 'cancelled', marcamos como 'merged' (transferido), registrando histórico
                $sourceOrder->update([
                    'status' => 'merged',
                    'notes'  => trim(($sourceOrder->notes ? $sourceOrder->notes . ' | ' : '') . "Itens transferidos para {$destOrder->customer_name} (#{$destOrder->id})"),
                ]);

                // Se a mesa de origem não tiver mais nenhum pedido ativo, libera a mesa física
                $this->freeTableIfEmpty($sourceOrder);
            }

            // 4. Recalcula a comanda de destino
            $this->recalculateOrderTotals($destOrder);

            // Garante que a mesa de destino fique marcada como ocupada
            $this->occupyDestinationTable($destOrder);

            // 5. Transmissão em tempo real para os terminais
            try {
                broadcast(new OrderUpdated($sourceOrder->fresh(['items', 'payments'])));
                broadcast(new OrderUpdated($destOrder->fresh(['items', 'payments'])));
            } catch (\Throwable $e) {}
        });
    }

    private function recalculateOrderTotals(Order $order): void
    {
        $subtotal = (float) $order->items()
            ->where('status', '!=', 'cancelled')
            ->sum('total_price');

        $deliveryFee = (float) ($order->delivery_fee ?? 0);
        $discount    = (float) ($order->discount ?? 0);

        // Mantém a taxa de serviço proporcional se houver
        $serviceFee = 0.00;
        if ((float) $order->service_fee > 0 && $subtotal > 0) {
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

    private function resolveDestinationOrder(array $data): Order
    {
        if ($data['destination_type'] === 'table') {
            $intent = $data['destination_order_intent'] ?? null;

            // Se o usuário selecionou uma comanda específica existente
            if (is_numeric($intent)) {
                $destOrder = Order::findOrFail((int) $intent);

                if (in_array($destOrder->status, ['closed', 'cancelled', 'merged'])) {
                    throw new \Exception("A comanda de destino (#{$destOrder->id}) já está finalizada. Selecione outra comanda ou abra uma nova.");
                }

                return $destOrder;
            }

            // Resolução da mesa de destino por ID ou Nome
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

            // 🚀 Se a intenção NÃO for criar uma subconta isolada, reaproveita a comanda ativa da mesa
            if ($intent !== 'new_subaccount') {
                $existingActiveOrder = Order::where(function ($q) use ($table) {
                        $q->where('dining_table_id', $table->id)
                          ->orWhere('table_number', $table->name);
                    })
                    ->whereNotIn('status', ['closed', 'cancelled', 'merged', 'completed'])
                    ->latest('id')
                    ->first();

                if ($existingActiveOrder) {
                    return $existingActiveOrder;
                }
            }

            // Caso a mesa esteja livre ou tenha sido solicitado subconta, cria a comanda de destino
            $customerName = ($intent === 'new_subaccount' && !empty($data['new_subaccount_name']))
                ? trim($data['new_subaccount_name'])
                : "Mesa " . $tableName;

            return Order::create([
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
                "employee_id"     => auth()->id(),
            ]);
        }

        $type = ($data['destination_order_intent'] ?? '') === 'new_delivery' ? 'delivery' : 'counter';

        return Order::create([
            "customer_name"  => "Novo " . ucfirst($type),
            "type"           => $type,
            "status"         => "pending",
            "payment_status" => "pending",
            "subtotal"       => 0,
            "discount"       => 0,
            "delivery_fee"   => 0,
            "service_fee"    => 0,
            "total"          => 0,
            "employee_id"    => auth()->id(),
        ]);
    }

    private function freeTableIfEmpty(Order $sourceOrder): void
    {
        $table = $sourceOrder->diningTable
            ?? ($sourceOrder->dining_table_id ? DiningTable::find($sourceOrder->dining_table_id) : null)
            ?? (!empty($sourceOrder->table_number) ? DiningTable::where('name', $sourceOrder->table_number)->first() : null);

        if (!$table) return;

        $hasOtherActive = Order::where(function ($q) use ($table) {
                $q->where('dining_table_id', $table->id)
                  ->orWhere('table_number', $table->name);
            })
            ->where('id', '!=', $sourceOrder->id)
            ->whereNotIn('status', ['closed', 'cancelled', 'merged', 'completed'])
            ->exists();

        if (!$hasOtherActive) {
            $table->update(['status' => 'available']);
            app(TablesService::class)->split($table);

            try {
                broadcast(new DiningTableUpdated($table->fresh()));
            } catch (\Throwable $e) {}
        }
    }

    private function occupyDestinationTable(Order $destOrder): void
    {
        if ($destOrder->type !== 'table') return;

        $table = $destOrder->diningTable
            ?? ($destOrder->dining_table_id ? DiningTable::find($destOrder->dining_table_id) : null)
            ?? (!empty($destOrder->table_number) ? DiningTable::where('name', $destOrder->table_number)->first() : null);

        if ($table && $table->status !== 'occupied') {
            $table->update(['status' => 'occupied']);

            try {
                broadcast(new DiningTableUpdated($table->fresh()));
            } catch (\Throwable $e) {}
        }
    }
}
