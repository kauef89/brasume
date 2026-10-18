<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\DiningTable;
use App\Models\CompanySetting;
use App\Events\OrderUpdated;
use App\Events\DiningTableUpdated;
use App\Services\RestaurantOps\TablesService;
use Illuminate\Support\Facades\DB;

class OrderTransferService
{
    public function execute(array $data): void
    {
        DB::transaction(function () use ($data) {
            $sourceOrder = Order::lockForUpdate()->findOrFail($data['source_order_id']);
            $itemIds = $data['item_ids'];

            // 1. Busca e valida os itens principais
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

                // Impede transferir um subitem/adicional sem o seu item principal
                if ($item->parent_item_id && !in_array($item->parent_item_id, $itemIds)) {
                    throw new \Exception("Não é possível transferir o adicional '{$item->product_name}' sem transferir o prato principal.");
                }
            }

            // Captura os filhos imediatos de itens compostos/combos
            $childItems = OrderItem::where('order_id', $sourceOrder->id)
                ->whereIn('parent_item_id', $mainItems->pluck('id'))
                ->lockForUpdate()
                ->get();

            // 2. Resolve ou localiza a comanda de destino
            $destOrder = $this->resolveDestinationOrder($data, $sourceOrder);

            // Trava para evitar transferência para a mesma comanda
            if ($sourceOrder->id === $destOrder->id) {
                throw new \Exception("A comanda de destino não pode ser a mesma de origem.");
            }

            // 3. Transfere os itens para a comanda de destino
            $allItemsToTransfer = $mainItems->pluck('id')->merge($childItems->pluck('id'));
            OrderItem::whereIn('id', $allItemsToTransfer)->update(['order_id' => $destOrder->id]);

            // 4. Recalcula a comanda de origem
            $this->recalculateOrderTotals($sourceOrder);

            // Verifica itens ativos restantes
            $remainingActiveItems = $sourceOrder->items()
                ->where('status', '!=', 'cancelled')
                ->count();

            if ($remainingActiveItems === 0) {
                // Comanda de origem agora recebe o status elegante de transferência
                $sourceOrder->update([
                    'status' => 'transferred',
                    'notes'  => trim(($sourceOrder->notes ? $sourceOrder->notes . ' | ' : '') . "Itens transferidos para {$destOrder->customer_name} (#{$destOrder->id})"),
                ]);

                // Libera a mesa física de origem
                $this->freeTableIfEmpty($sourceOrder);
            }

            // 5. Recalcula a comanda de destino herdando a taxa de serviço se aplicável
            $this->recalculateOrderTotals($destOrder, $sourceOrder);

            // Garante que a mesa de destino fique marcada como ocupada
            $this->occupyDestinationTable($destOrder);

            // 6. Transmissão em tempo real
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
            ->sum('total_price');

        $deliveryFee = (float) ($order->delivery_fee ?? 0);
        $discount    = (float) ($order->discount ?? 0);

        // Calcula taxa de serviço proporcional ou herdada
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

            // Se o usuário selecionou uma comanda específica existente
            if (is_numeric($intent)) {
                $destOrder = Order::lockForUpdate()->findOrFail((int) $intent);

                if (in_array($destOrder->status, ['closed', 'cancelled', 'merged', 'completed', 'transferred'])) {
                    throw new \Exception("A comanda de destino (#{$destOrder->id}) já está finalizada ou transferida.");
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

            // Se NÃO for criar uma subconta isolada, reaproveita a comanda ativa existente
            if ($intent !== 'new_subaccount') {
                $existingActiveOrder = Order::where(function ($q) use ($table) {
                        $q->where('dining_table_id', $table->id)
                          ->orWhere('table_number', $table->name);
                    })
                    ->where('id', '!=', $sourceOrder->id)
                    ->whereNotIn('status', ['closed', 'cancelled', 'merged', 'completed'])
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if ($existingActiveOrder) {
                    return $existingActiveOrder;
                }
            }

            // Cria a comanda preservando o turno de caixa (cash_shift_id)
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

        $hasOtherActive = Order::where(function ($q) use ($table) {
                $q->where('dining_table_id', $table->id)
                  ->orWhere('table_number', $table->name);
            })
            ->where('id', '!=', $sourceOrder->id)
            ->whereNotIn('status', ['closed', 'cancelled', 'merged', 'completed'])
            ->exists();

        if (!$hasOtherActive) {
            $table->update(['status' => 'available']);

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
