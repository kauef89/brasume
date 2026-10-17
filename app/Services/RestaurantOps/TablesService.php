<?php

namespace App\Services\RestaurantOps;

use App\Models\DiningTable;
use App\Models\Order;
use App\Events\OrderUpdated;
use App\Events\DiningTableUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TablesService
{
    public function updateStatus(DiningTable $table, string $status): void
    {
        DB::transaction(function () use ($table, $status) {
            $lockedTable = DiningTable::where('id', $table->id)->lockForUpdate()->firstOrFail();
            $lockedTable->update(['status' => $status]);

            DB::afterCommit(fn () => broadcast(new DiningTableUpdated($lockedTable->fresh())));
        });
    }

    public function move(DiningTable $sourceTable, int $targetTableId): DiningTable
    {
        if ((int) $sourceTable->id === $targetTableId) {
            throw ValidationException::withMessages([
                'target_table_id' => 'A mesa de destino deve ser diferente da mesa atual.',
            ]);
        }

        return DB::transaction(function () use ($sourceTable, $targetTableId) {
            // Trava as duas mesas para evitar race conditions
            $sourceTable = DiningTable::where('id', $sourceTable->id)->lockForUpdate()->firstOrFail();
            $targetTable = DiningTable::where('id', $targetTableId)->lockForUpdate()->firstOrFail();

            // 1. Impede mover para uma mesa que já está ocupada
            if ($targetTable->status === 'occupied') {
                throw ValidationException::withMessages([
                    'target_table_id' => "A mesa {$targetTable->name} já está ocupada.",
                ]);
            }

            // 2. Busca pedidos ativos associados à mesa de origem (por ID ou número)
            $orders = Order::where(function ($query) use ($sourceTable) {
                    $query->where('dining_table_id', $sourceTable->id)
                          ->orWhere('table_number', $sourceTable->name);
                })
                ->whereNotIn('status', ['closed', 'cancelled', 'completed', 'delivered'])
                ->where('payment_status', '!=', 'paid')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                throw ValidationException::withMessages([
                    'target_table_id' => "Nenhuma comanda ativa encontrada na mesa {$sourceTable->name}.",
                ]);
            }

            // 3. Atualiza os pedidos transferindo a titularidade e o nome padrão
            foreach ($orders as $order) {
                $updatePayload = [
                    'table_number'    => $targetTable->name,
                    'dining_table_id' => $targetTable->id,
                ];

                // Se o nome do cliente for genérico da mesa anterior, renomeia para a nova mesa
                $oldDefaultNames = [
                    $sourceTable->name,
                    "Mesa {$sourceTable->name}",
                    "Mesa " . ltrim($sourceTable->name, '0'),
                ];

                if (!$order->customer_name || in_array($order->customer_name, $oldDefaultNames)) {
                    $updatePayload['customer_name'] = "Mesa {$targetTable->name}";
                }

                $order->update($updatePayload);

                DB::afterCommit(fn () => broadcast(new OrderUpdated($order->fresh())));
            }

            // 4. Alterna os estados das mesas
            $sourceTable->update(['status' => 'available']);
            $targetTable->update(['status' => 'occupied']);

            DB::afterCommit(function () use ($sourceTable, $targetTable) {
                broadcast(new DiningTableUpdated($sourceTable->fresh()));
                broadcast(new DiningTableUpdated($targetTable->fresh()));
            });

            return $targetTable;
        });
    }

    public function join(DiningTable $primaryTable, array $secondaryTableIds): void
    {
        $secondaryIds = array_filter(
            $secondaryTableIds,
            fn ($id) => (int) $id !== (int) $primaryTable->id
        );

        if (empty($secondaryIds)) {
            throw ValidationException::withMessages([
                'secondary_table_ids' => 'Selecione ao menos uma mesa secundária diferente da principal.',
            ]);
        }

        DB::transaction(function () use ($primaryTable, $secondaryIds) {
            $primaryTable = DiningTable::where('id', $primaryTable->id)->lockForUpdate()->firstOrFail();
            $secondaryTables = DiningTable::whereIn('id', $secondaryIds)->lockForUpdate()->get();

            foreach ($secondaryTables as $secTable) {
                $secTable->update([
                    'parent_table_id' => $primaryTable->id,
                    'status'          => 'occupied',
                ]);

                // As comandas mantêm seus dining_table_id originais (mantém a rastreabilidade)
                // O Vue/Frontend deve usar as props da mesa "Pai" e das mesas filhas.

                DB::afterCommit(fn () => broadcast(new DiningTableUpdated($secTable->fresh())));
            }

            $primaryTable->update(['status' => 'occupied']);

            DB::afterCommit(fn () => broadcast(new DiningTableUpdated($primaryTable->fresh())));
        });
    }

    public function split(DiningTable $table): void
    {
        DB::transaction(function () use ($table) {
            $table = DiningTable::where('id', $table->id)->lockForUpdate()->firstOrFail();

            // Se for uma mesa secundária, separa só ela
            if ($table->parent_table_id) {
                $table->update([
                    'parent_table_id' => null,
                    'status'          => $table->activeOrders()->exists() ? 'occupied' : 'available',
                ]);
                DB::afterCommit(fn () => broadcast(new DiningTableUpdated($table->fresh())));
                return;
            }

            // Se for mesa principal, separa todas as secundárias
            $secondaries = DiningTable::where('parent_table_id', $table->id)->lockForUpdate()->get();

            foreach ($secondaries as $sec) {
                $sec->update([
                    'parent_table_id' => null,
                    'status'          => $sec->activeOrders()->exists() ? 'occupied' : 'available',
                ]);
                DB::afterCommit(fn () => broadcast(new DiningTableUpdated($sec->fresh())));
            }

            // Atualiza status da mesa principal
            $hasActiveOrders = $table->activeOrders()->exists();
            $table->update(['status' => $hasActiveOrders ? 'occupied' : 'available']);

            DB::afterCommit(fn () => broadcast(new DiningTableUpdated($table->fresh())));
        });
    }
}
