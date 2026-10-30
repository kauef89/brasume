<?php

namespace App\Services\RestaurantOps;

use App\Models\Order;
use App\Models\User;
use App\Models\CashShift;
use App\Events\OrderUpdated;
use App\Notifications\OrderReadyForDeliveryNotification;
use App\Services\Customers\CustomerMappingService;
use App\Services\Orders\ComboProrationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

class OperationsDashboardService
{
    public function __construct(protected CustomerMappingService $customerMapper) {}

    public function getActiveOrdersDashboard(): array
    {
        $shift = CashShift::where('status', 'open')->latest('opened_at')->first();
        $shiftStart = $shift ? $shift->opened_at : now()->startOfDay();

        $terminalStatuses = ['closed', 'cancelled', 'completed', 'merged'];

        $orders = Order::with([
                    "items.modifiers", "items.subItems.modifiers", "items.product.category",
                    "items.kitchen", "items.product.kitchen",
                    "customer.addresses.street", "customer.addresses.neighborhood",
                    "addressModel.street", "addressModel.neighborhood",
                    "payments.items.modifiers",
                    "fiscalDocument",
                    "diningTable"
                ])
                ->where(function ($query) use ($shiftStart, $terminalStatuses) {
                    $query->whereNotIn("status", $terminalStatuses)
                            ->orWhere(function ($subQuery) use ($shiftStart, $terminalStatuses) {
                                $subQuery->whereIn("status", $terminalStatuses)
                                        ->where('updated_at', '>=', $shiftStart);
                            });
                })
                ->orderBy("created_at", "asc")
                ->get();

        $mappedOrders = $orders->map(function ($order) {
            $fullAddress = null;
            $neighborhoodName = null;

            if ($order->type === "delivery" && ($order->address_id || $order->address || $order->address_snapshot)) {
                $addr = $order->address_snapshot ?? $order->address ?? $order->getRelationValue('address') ?? $order->addressModel;

                if (is_array($addr)) {
                    $neighborhoodName = is_array($addr['neighborhood'] ?? null)
                        ? ($addr['neighborhood']['name'] ?? null)
                        : ($addr['neighborhood'] ?? null);

                    $streetName = is_array($addr['street'] ?? null)
                        ? ($addr['street']['name'] ?? null)
                        : ($addr['street'] ?? null);

                    $number = !empty($addr['no_number']) ? 'S/N' : ($addr['number'] ?? '');
                    $complement = !empty($addr['complement']) ? "({$addr['complement']})" : '';

                    $fullAddress = $addr['full_address'] ?? trim("{$streetName}, {$number} - {$neighborhoodName} {$complement}");
                } elseif (is_object($addr)) {
                    $neighborhoodName = $addr->neighborhood->name ?? ($addr->neighborhood ?? null);
                    $streetName = $addr->street->name ?? ($addr->street ?? null);
                    $number = $addr->no_number ? 'S/N' : ($addr->number ?? '');
                    $complement = $addr->complement ? "({$addr->complement})" : '';

                    $fullAddress = $addr->full_address ?? trim("{$streetName}, {$number} - {$neighborhoodName} {$complement}");
                }
            }

            $deliveryMethods = is_string($order->payment_intent_methods)
                ? json_decode($order->payment_intent_methods, true)
                : $order->payment_intent_methods;

            if (empty($deliveryMethods) && $order->payments->isNotEmpty()) {
                $deliveryMethods = $order->payments->map(fn($p) => [
                    'method_id'   => $p->payment_method_id,
                    'method_name' => $p->method,
                    'amount'      => (float) $p->amount,
                    'change_target' => (float) $order->delivery_change_target,
                ])->toArray();
            }

            // 🚀 Rateio proporcional para combos
            $comboParents = $order->items->filter(fn($i) => !$i->parent_item_id && $order->items->contains('parent_item_id', $i->id));
            $orderProrations = [];
            foreach ($comboParents as $parent) {
                $children = $order->items->filter(fn($i) => $i->parent_item_id === $parent->id);
                $orderProrations += ComboProrationHelper::calculateProration($parent, $children, $order->type);
            }

            return [
                "id"                    => $order->id,
                "driver_id"             => $order->driver_id,
                "customer_name"         => $order->customer_name,
                "customer_id"           => $order->customer_id,
                "customer"              => $this->customerMapper->mapOrderCustomer($order->customer),
                "dining_table_id"       => $order->dining_table_id,
                "table_number"          => $order->table_number,
                "type"                  => $order->type,
                "status"                => $order->status,
                "payment_status"        => $order->payment_status,
                "payment_intent_status" => $order->payment_intent_status,
                "payment_intent_methods"=> $deliveryMethods,
                "payments"              => $order->payments->map(fn($p) => [
                    "id"                => $p->id,
                    "payment_method_id" => $p->payment_method_id,
                    "method"            => $p->method,
                    "amount"            => (float) $p->amount,
                    "is_paid"           => (bool) $p->is_paid,
                    "created_at"        => $p->created_at?->toIso8601String(),
                    "items"             => $p->items->map(function ($pi) use ($p) {
                        $pivotAmount = (float) $pi->pivot->amount;
                        $resolvedAmount = $pivotAmount > 0
                            ? $pivotAmount
                            : ($p->items->count() === 1 ? (float) $p->amount : (float) $pi->total_price);

                        return [
                            "id"           => $pi->id,
                            "name"         => $pi->product_name,
                            "product_name" => $pi->product_name,
                            "quantity"     => (float) $pi->pivot->quantity,
                            "total_amount" => $resolvedAmount,
                            "notes"        => $pi->notes,
                            "modifiers"    => $pi->modifiers->map(fn($m) => [
                                "id"       => $m->modifier_option_id,
                                "name"     => $m->name,
                                "qty"      => (float) $m->quantity,
                                "price"    => (float) $m->price
                            ])->values()->all(),
                            "pivot"        => [
                                "quantity" => (float) $pi->pivot->quantity,
                                "amount"   => $resolvedAmount,
                            ]
                        ];
                    })->values()->all(),
                ])->values(),
                "payment_method_name"   => !empty($deliveryMethods) ? ($deliveryMethods[0]['method_name'] ?? null) : ($order->payments->first()?->method ?? null),
                "total"                 => (float) $order->total,
                "created_at"            => $order->created_at ? $order->created_at->toISOString() : null,
                "time"                  => $order->created_at ? $order->created_at->format("H:i") : null,
                "minutes_active"        => $order->created_at ? $order->created_at->diffInMinutes(now()) : 0,
                "address"               => $fullAddress,
                "neighborhood"          => $neighborhoodName,
                "phone"                 => $order->customer_phone ?? $order->customer?->phone ?? null,
                "items"                 => $order->items->map(function ($i) use ($orderProrations) {
                    $isChild = (bool) $i->parent_item_id;
                    $prorationData = $isChild ? ($orderProrations[$i->id] ?? null) : null;
                    $catalogPrice = $prorationData ? $prorationData['catalog_unit_price'] : (float) $i->unit_price;
                    $allocatedUnitPrice = $prorationData ? $prorationData['prorated_unit_price'] : (float) $i->unit_price;

                    return [
                        "id"                   => $i->id,
                        "parent_item_id"       => $i->parent_item_id,
                        "product_id"           => $i->product_id,
                        "name"                 => $i->product_name,
                        "quantity"             => (float) $i->quantity,
                        "paid_qty"             => (float) ($i->paid_qty ?? 0),
                        "unit_price"           => (float) $i->unit_price,
                        "total_price"          => (float) $i->total_price,
                        "catalog_price"        => $catalogPrice,
                        "allocated_unit_price" => $allocatedUnitPrice,
                        "effective_unit_price" => $allocatedUnitPrice,
                        "status"               => $i->status,
                        "production_status"    => $i->production_status ?? $i->status,
                        "category_name"        => $i->product?->category?->name ?? null,
                        "kitchen_type"         => $i->kitchen?->type ?? $i->product?->kitchen?->type ?? null,
                        "operator_id"          => $i->operator_id ?? $i->prepared_by_id ?? null,
                        "operator_name"        => $i->operator?->name ?? $i->preparedBy?->name ?? null,
                        "created_at"           => $i->created_at ? $i->created_at->toISOString() : null,
                        "fired_at"             => $i->fired_at ? $i->fired_at->toISOString() : null,
                        "is_combo_item"        => $i->is_combo_item,
                        "modifiers"            => $i->modifiers->map(fn($m) => [
                            "id"               => $m->modifier_option_id,
                            "name"             => $m->name,
                            "qty"              => (float) $m->quantity,
                            "price"            => (float) $m->price
                        ])->values(),
                    ];
                })->values(),
            ];
        });

        return ["initialOrders" => $mappedOrders];
    }

    public function updateOrderStatus(Order $order, mixed $statusOrData, bool $stampIntermediates = false): Order
    {
        $newStatus = is_array($statusOrData) ? ($statusOrData['status'] ?? $order->status) : $statusOrData;
        $stamp = is_array($statusOrData) ? ($statusOrData['stamp_intermediates'] ?? $stampIntermediates) : $stampIntermediates;

        return DB::transaction(function () use ($order, $newStatus, $stamp) {
            $order->status = $newStatus;

            if ($newStatus === 'preparing' || ($stamp && in_array($newStatus, ['ready', 'dispatched']))) {
                if (!$order->started_preparing_at) {
                    $order->started_preparing_at = now();
                }
                $order->items()->where('production_status', 'pending')->update(['production_status' => 'preparing']);
            }

            if ($newStatus === 'ready') {
                if (true) {
                    $order->ready_at = now();
                }
                $order->items()->whereIn('production_status', ['pending', 'preparing'])->update(['production_status' => 'ready']);

                // 🛵 DISPARO PUSH: Notifica todos os motoboys que há entrega pronta no balcão
                if ($order->type === 'delivery') {
                    try {
                        $drivers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['driver', 'motoboy', 'entregador']))->get();
                        if ($drivers->isNotEmpty()) {
                            Notification::send($drivers, new OrderReadyForDeliveryNotification($order));
                        }
                    } catch (\Throwable $e) {
                        Log::warning("⚠️ [WebPush] Falha ao notificar motoboys sobre pedido #{$order->id}: " . $e->getMessage());
                    }
                }
            }

            if ($newStatus === 'dispatched') {
                if (true) {
                    $order->dispatched_at = now();
                }
            }

            if (in_array($newStatus, ['closed', 'completed'])) {
                if (true) {
                    $order->closed_at = now();
                }

                // 🚀 Libera a mesa automaticamente se não houver outros pedidos ativos nela
                if ($order->type === 'table' && $order->dining_table_id) {
                    $otherActive = Order::where('dining_table_id', $order->dining_table_id)
                        ->where('id', '!=', $order->id)
                        ->whereNotIn('status', ['closed', 'cancelled'])
                        ->exists();

                    if (!$otherActive) {
                        DiningTable::where('id', $order->dining_table_id)->update(['status' => 'available']);
                    }
                }
            }

            $order->save();

            broadcast(new OrderUpdated($order->fresh()));

            return $order;
        });
    }

    public function getManagerShiftSummary(?User $user): ?array
    {
        // Garante que só quem é da diretoria/gerência veja os dados
        if (!$user || !$user->hasAnyRole(['admin', 'gerente', 'manager', 'owner'])) return null;

        // Pega o turno aberto... ou, se estiver fechado, pega o último turno!
        $shift = CashShift::where('status', 'open')->latest('opened_at')->first()
              ?? CashShift::latest('closed_at')->first();

        if (!$shift) return null;

        $referenceDate = $shift->opened_at;
        $endDate = $shift->closed_at ?? now();

        $closedOrders = Order::whereBetween('created_at', [$referenceDate, $endDate])
            ->whereIn('status', ['closed', 'completed'])
            ->get();

        $revenue = $closedOrders->sum('total');
        $count = $closedOrders->count();

        // 🚀 Conta os pedidos e soma os valores corretamenta para o dashboard
        $deliveryOrders = $closedOrders->where('type', 'delivery');
        $tableOrders = $closedOrders->where('type', 'table');
        $counterOrders = $closedOrders->where('type', 'counter');

        return [
            'shift_status'  => $shift->status,
            'revenue'       => $revenue,
            'order_count'   => $count,
            'avg_ticket'    => $count > 0 ? $revenue / $count : 0,
            'expected_cash' => $shift->closing_balance_expected ?? $shift->opening_balance ?? 0,
            'by_type'       => [
                'delivery'  => ['amount' => $deliveryOrders->sum('total'), 'count' => $deliveryOrders->count()],
                'table'     => ['amount' => $tableOrders->sum('total'), 'count' => $tableOrders->count()],
                'counter'   => ['amount' => $counterOrders->sum('total'), 'count' => $counterOrders->count()],
            ],
            'shift_start'   => $shift->opened_at->format('H:i'),
            'shift_model'   => $shift->toArray() // Manda o shift inteiro para a view
        ];
    }
}
