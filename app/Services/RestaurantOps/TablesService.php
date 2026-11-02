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
            $sourceTable = DiningTable::where('id', $sourceTable->id)->lockForUpdate()->firstOrFail();
            $targetTable = DiningTable::where('id', $targetTableId)->lockForUpdate()->firstOrFail();

            if ($targetTable->status === 'occupied') {
                throw ValidationException::withMessages([
                    'target_table_id' => "A mesa {$targetTable->name} já está ocupada.",
                ]);
            }

            // 1. Obtém todas as mesas do grupo de origem (se estiver agrupada)
            $sourceGroupIds = $sourceTable->getGroupTableIds();

            // 2. Procura todos os pedidos ativos nas mesas do grupo de origem
            $orders = Order::where(function ($query) use ($sourceGroupIds, $sourceTable) {
                    $query->whereIn('dining_table_id', $sourceGroupIds)
                          ->orWhere('table_number', $sourceTable->name);
                })
                ->whereNotIn('status', ['closed', 'cancelled', 'completed', 'delivered', 'transferred'])
                ->where('payment_status', '!=', 'paid')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                throw ValidationException::withMessages([
                    'target_table_id' => "Nenhuma comanda ativa encontrada na mesa {$sourceTable->name}.",
                ]);
            }

            // 3. Atualiza os pedidos para a nova mesa
            foreach ($orders as $order) {
                $updatePayload = [
                    'table_number'    => $targetTable->name,
                    'dining_table_id' => $targetTable->id,
                ];

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

            // 4. Se a mesa de origem tinha filhas acopladas, re-vincula as filhas à nova mesa-mãe
            if ($sourceTable->linkedTables()->exists()) {
                $childIds = $sourceTable->linkedTables()->pluck('id');
                DiningTable::whereIn('id', $childIds)->update(['parent_table_id' => $targetTable->id]);
            }

            // 5. Atualiza os estados das mesas
            DiningTable::whereIn('id', $sourceGroupIds)->update(['status' => 'available', 'parent_table_id' => null]);
            $targetTable->update(['status' => 'occupied']);

            DB::afterCommit(function () use ($sourceGroupIds, $targetTable) {
                foreach ($sourceGroupIds as $sId) {
                    $st = DiningTable::find($sId);
                    if ($st) broadcast(new DiningTableUpdated($st));
                }
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

            // 🔍 Avalia se alguma das mesas envolvidas já possui pedidos ativos
            $allTables = collect([$primaryTable])->merge($secondaryTables);
            $hasActiveOrders = $this->hasActiveOrders($allTables->all());

            // Só marca como 'occupied' se houver consumo real; layout físico permanece 'available'
            $statusToApply = $hasActiveOrders ? 'occupied' : 'available';

            foreach ($secondaryTables as $secTable) {
                $secTable->update([
                    'parent_table_id' => $primaryTable->id,
                    'status'          => $statusToApply,
                ]);

                DB::afterCommit(fn () => broadcast(new DiningTableUpdated($secTable->fresh())));
            }

            $primaryTable->update(['status' => $statusToApply]);

            DB::afterCommit(fn () => broadcast(new DiningTableUpdated($primaryTable->fresh())));
        });
    }

    public function split(DiningTable $table): void
    {
        DB::transaction(function () use ($table) {
            $table = DiningTable::where('id', $table->id)->lockForUpdate()->firstOrFail();

            // Se for uma mesa secundária desvinculada individualmente
            if ($table->parent_table_id) {
                $parent = DiningTable::where('id', $table->parent_table_id)->lockForUpdate()->first();

                $table->update([
                    'parent_table_id' => null,
                    'status'          => $this->hasActiveOrders($table) ? 'occupied' : 'available',
                ]);
                DB::afterCommit(fn () => broadcast(new DiningTableUpdated($table->fresh())));

                // Recalcula o status do grupo remanescente na mesa principal
                if ($parent) {
                    $remainingChildren = DiningTable::where('parent_table_id', $parent->id)->get();
                    $remainingGroup = collect([$parent])->merge($remainingChildren);
                    $parent->update([
                        'status' => $this->hasActiveOrders($remainingGroup->all()) ? 'occupied' : 'available',
                    ]);
                    DB::afterCommit(fn () => broadcast(new DiningTableUpdated($parent->fresh())));
                }

                return;
            }

            // Se for a mesa principal, desfaz o agrupamento de todas as filhas
            $secondaries = DiningTable::where('parent_table_id', $table->id)->lockForUpdate()->get();

            foreach ($secondaries as $sec) {
                $sec->update([
                    'parent_table_id' => null,
                    'status'          => $this->hasActiveOrders($sec) ? 'occupied' : 'available',
                ]);
                DB::afterCommit(fn () => broadcast(new DiningTableUpdated($sec->fresh())));
            }

            // Atualiza a mesa principal de forma isolada
            $table->update([
                'status' => $this->hasActiveOrders($table) ? 'occupied' : 'available',
            ]);

            DB::afterCommit(fn () => broadcast(new DiningTableUpdated($table->fresh())));
        });
    }

    /**
     * 🛡️ Checa se há comandas abertas/não pagas vinculadas à mesa ou lista de mesas.
     */
    protected function hasActiveOrders(DiningTable|array $tables): bool
    {
        $tableList = is_array($tables) ? $tables : [$tables];
        $tableIds = [];
        $tableNames = [];

        foreach ($tableList as $t) {
            if ($t instanceof DiningTable) {
                $tableIds[] = $t->id;
                $tableNames[] = $t->name;
            } elseif (is_numeric($t)) {
                $tableIds[] = (int) $t;
            }
        }

        return Order::where(function ($query) use ($tableIds, $tableNames) {
                $query->whereIn('dining_table_id', $tableIds);
                if (!empty($tableNames)) {
                    $query->orWhereIn('table_number', $tableNames);
                }
            })
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->exists();
    }
}
