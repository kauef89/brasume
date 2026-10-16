<?php

namespace App\Services\RestaurantOps;

use App\Models\Kitchen;
use App\Models\OrderItem;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\ModifierOption;
use App\Models\AccountPayable;
use App\Models\CompanySetting;
use App\Services\Catalog\InventoryService;
use Illuminate\Support\Facades\DB;

class KdsService
{
    public function __construct(protected InventoryService $inventoryService) {}

    public function getBoardData(string|int $activeKitchenId): array
    {
        $settings = CompanySetting::getCached();
        $user = auth()->user();
        $employee = $user?->employee;

        $kitchensQuery = Kitchen::where('is_active', true);

        if ($settings && $settings->kds_access_mode === 'individual' && $employee) {
            $allowedIds = $employee->kitchens()->pluck('kitchens.id')->toArray();
            $kitchensQuery->whereIn('id', $allowedIds);
        }

        $kitchens = $kitchensQuery->get();
        $allowedKitchenIds = $kitchens->pluck('id')->toArray();

        if ($activeKitchenId !== 'all' && !in_array((int)$activeKitchenId, $allowedKitchenIds)) {
            $activeKitchenId = 'all';
        }

        $currentShift = CashShift::where('status', 'open')->latest('opened_at')->first();

        if (!$currentShift || $kitchens->isEmpty()) {
            return [
                'kitchens' => $kitchens,
                'activeKitchenId' => $activeKitchenId === 'all' ? 'all' : (int) $activeKitchenId,
                'initialItems' => [],
                'shiftActive' => $currentShift ? true : false
            ];
        }

        $query = OrderItem::with(['order:id,customer_name,type,table_number', 'modifiers'])
            ->where('status', 'active')
            ->whereNotIn('production_status', ['delivered', 'cancelled'])
            ->where('created_at', '>=', $currentShift->opened_at);

        if ($activeKitchenId !== 'all') {
            $query->where('kitchen_id', $activeKitchenId);
        } else {
            if ($settings && $settings->kds_access_mode === 'individual') {
                $query->whereIn('kitchen_id', $allowedKitchenIds);
            }
        }

        return [
            'kitchens' => $kitchens,
            'activeKitchenId' => $activeKitchenId === 'all' ? 'all' : (int) $activeKitchenId,
            'initialItems' => $query->orderBy('created_at', 'asc')->get(),
            'shiftActive' => true
        ];
    }

    public function getItems(string|int $activeKitchenId)
    {
        $boardData = $this->getBoardData($activeKitchenId);
        return $boardData['initialItems'] ?? [];
    }

    public function updateItemStatus(OrderItem $item, string $status): void
    {
        $updateData = ['production_status' => $status];

        if ($status === 'preparing') {
            $updateData['prepared_by_id'] = auth()->id();
        }

        $item->update($updateData);

        // 🚀 CORREÇÃO: Garante a passagem do Model Order completo
        $order = $item->order ?? \App\Models\Order::find($item->order_id);
        if ($order) {
            event(new \App\Events\OrderUpdated($order));
        }
    }

    public function cancelItemAndRefund(OrderItem $item, array $data): void
    {
        DB::transaction(function () use ($item, $data) {
            $order = $item->order;
            $itemTotal = $item->total_price;

            $order->subtotal -= $itemTotal;

            $serviceFeePercentage = $order->subtotal > 0 ? ($order->service_fee / ($order->subtotal + $itemTotal)) : 0;
            $order->service_fee = round($order->subtotal * $serviceFeePercentage, 2);

            $order->total = $order->subtotal + $order->delivery_fee + $order->service_fee - $order->discount;
            $order->save();

            $totalPaid = $order->payments()->sum('amount');
            if ($totalPaid > $order->total) {
                AccountPayable::create([
                    'supplier_name' => $order->customer_name ?? 'Cliente Avulso (Ped #' . $order->id . ')',
                    'description' => "Crédito de Cancelamento - Pedido #{$order->id} ({$item->product_name})",
                    'amount' => $itemTotal,
                    'due_date' => now()->toDateString(),
                    'status' => 'pending',
                    'accounting_category' => 'adm',
                    'observations' => 'Gerado automaticamente por um cancelamento na Cozinha (KDS) de um pedido já pago.',
                ]);
            }

            $item->update([
                'status' => 'cancelled',
                'production_status' => 'cancelled',
                'cancel_reason' => 'Cozinha: ' . $data['reason'],
            ]);

            if (!empty($data['block_sales']) && !empty($data['block_id'])) {
                if ($data['block_type'] === 'main') {
                    $product = Product::find($data['block_id']);
                    if ($product) {
                        $product->update(['status' => 'out_of_stock']);

                        if ($product->track_inventory) {
                            event(new \App\Events\StockLowDetected($product, 'critical'));
                        }
                    }
                } elseif ($data['block_type'] === 'modifier') {
                    $modifierOption = ModifierOption::find($data['block_id']);
                    if ($modifierOption) {
                        if ($modifierOption->linked_product_id) {
                            $product = Product::find($modifierOption->linked_product_id);
                            if ($product) {
                                $product->update(['status' => 'out_of_stock']);

                                if ($product->track_inventory) {
                                    event(new \App\Events\StockLowDetected($product, 'critical'));
                                }
                            }
                        } else {
                            $modifierOption->update(['is_active' => false]);
                            $tenant = tenant('id') ?? 'central';
                            broadcast(new \App\Events\CatalogUpdated($tenant));
                        }
                    }
                }
            }

            // 🚀 CORREÇÃO: Passando o Model Order em vez da string $tenant
            event(new \App\Events\OrderUpdated($order));
        });
    }

    public function registerLoss(OrderItem $item, array $data, int $userId): void
    {
        $product = null;

        if ($data['type'] === 'main') {
            $product = Product::find($data['target_id']);
        } else {
            $modifierOption = ModifierOption::find($data['target_id']);
            if ($modifierOption && $modifierOption->linked_product_id) {
                $product = Product::find($modifierOption->linked_product_id);
            }
        }

        if (!$product && $data['type'] === 'main') {
            $product = Product::find($item->product_id);
        }

        if ($product) {
            $this->inventoryService->registerLoss(
                $product,
                $data['quantity'],
                'Cozinha: ' . $data['reason'],
                $data['notes'] ?? null,
                $userId,
                $item->id
            );
        }
    }
}
