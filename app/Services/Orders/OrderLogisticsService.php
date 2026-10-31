<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Models\Address;
use App\Models\DiningTable;
use App\Models\CompanySetting;
use App\Notifications\OrderReadyForDeliveryNotification;
use App\Events\OrderUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderLogisticsService
{
    public function __construct(
        protected OrderAddressResolver $addressResolver
    ) {}

    public function transformOrder(Order $order, array $data): Order
    {
        $newType = $data['new_type'] ?? $data['newType'] ?? $data['type'] ?? null;

        if (!$newType || !in_array($newType, ['table', 'counter', 'delivery'], true)) {
            throw new Exception("Tipo de pedido inválido ou não informado.");
        }

        return DB::transaction(function () use ($order, $newType, $data) {
            $order->type = $newType;

            if ($newType === 'table') {
                $tableId = $data['dining_table_id'] ?? $data['table_id'] ?? null;
                $tableName = $data['table_number'] ?? $data['identifier'] ?? null;

                if (!$tableId && $tableName) {
                    $table = DiningTable::where('name', $tableName)
                        ->orWhere('number', $tableName)
                        ->first();
                    $tableId = $table?->id;
                    $tableName = $table?->name ?? $tableName;
                } elseif ($tableId && empty($tableName)) {
                    $tableName = DiningTable::find($tableId)?->name;
                }

                $order->dining_table_id = $tableId;
                $order->table_number = $tableName;

                if (!empty($data['customer_name'])) {
                    $order->customer_name = $data['customer_name'];
                }

                $order->delivery_fee = 0;
                $order->driver_id = null;
                $order->address_id = null;
                $order->address_snapshot = null;
                $order->address = null;
                $order->payment_intent_status = null;
                $order->payment_intent_methods = null;

            } elseif ($newType === 'counter') {
                $order->dining_table_id = null;
                $order->table_number = null;
                $order->delivery_fee = 0;
                $order->driver_id = null;
                $order->address_id = null;
                $order->address_snapshot = null;
                $order->address = null;
                $order->payment_intent_status = null;
                $order->payment_intent_methods = null;

                $identifier = $data['identifier'] ?? $data['customer_name'] ?? null;
                if (!empty($identifier)) {
                    $order->customer_name = $identifier;
                }

            } elseif ($newType === 'delivery') {
                $order->dining_table_id = null;
                $order->table_number = null;

                if (empty($order->payment_intent_status) || $order->payment_intent_status === 'none') {
                    $order->payment_intent_status = 'pending_choice';
                }
            }

            $settings = CompanySetting::getCached() ?? CompanySetting::first();
            $serviceFee = $settings ? $settings->calculateServiceFee((float) $order->subtotal, $newType) : 0.00;
            $order->service_fee = $serviceFee;

            $deliveryFee = (float) ($order->delivery_fee ?? 0);
            $subtotal = (float) ($order->subtotal ?? 0);
            $discount = (float) ($order->discount ?? 0);

            $order->total = max(0, round(($subtotal + $serviceFee + $deliveryFee) - $discount, 2));
            $order->save();

            try {
                broadcast(new OrderUpdated($order));
            } catch (\Throwable $e) {
                Log::error("🚨 [WebSockets] Falha ao transmitir conversão do pedido #{$order->id}: " . $e->getMessage());
            }

            return $order;
        });
    }

    public function changeAddress(Order $order, int|string|null $addressId, ?float $deliveryFee = null, ?array $rawAddress = null): void
    {
        if ($order->type !== 'delivery') {
            $order->type = 'delivery';
        }

        $routingPayload = [
            'type'        => 'delivery',
            'address_id'  => $addressId,
            'address'     => $rawAddress,
        ];

        $resolved = $this->addressResolver->resolve(
            $routingPayload,
            $order->customer_id,
            (float) ($deliveryFee ?? $order->delivery_fee ?? 0)
        );

        $resolvedAddressId = $resolved['address_id'];
        $resolvedSnapshot = $resolved['snapshot'];

        if ($deliveryFee === null && $resolvedSnapshot) {
            $deliveryFee = (float) ($resolvedSnapshot['delivery_fee'] ?? 0);
        }

        $deliveryFee = (float) ($deliveryFee ?? $order->delivery_fee ?? 0);
        $newTotal = max(0, round(($order->subtotal + $order->service_fee + $deliveryFee) - $order->discount, 2));

        $order->update([
            'type'             => 'delivery',
            'address_id'       => $resolvedAddressId,
            'address_snapshot' => $resolvedSnapshot ?? $order->address_snapshot,
            'address'          => $resolvedSnapshot ?? $order->address,
            'delivery_fee'     => $deliveryFee,
            'total'            => $newTotal
        ]);

        try {
            broadcast(new OrderUpdated($order));
        } catch (\Throwable $e) {
            Log::error("🚨 [WebSockets] Falha ao transmitir endereço do pedido #{$order->id}: " . $e->getMessage());
        }
    }

    public function assignCourier(Order $order, int $driverId): void
    {
        if ($order->type !== 'delivery') {
            throw new Exception('Apenas pedidos de delivery podem receber entregadores.');
        }

        $order->update(['driver_id' => $driverId]);

        $driver = User::find($driverId);
        if ($driver) {
            // 🚀 Envia o Web Push dedicado ao motoboy com link direto para /team/driver
            try {
                $driver->notify(new OrderReadyForDeliveryNotification($order));
            } catch (\Throwable $e) {
                Log::warning("⚠️ [WebPush] Falha ao notificar motorista #{$driverId}: " . $e->getMessage());
            }
        }

        try {
            broadcast(new OrderUpdated($order));
        } catch (\Throwable $e) {
            Log::error("🚨 [WebSockets] Falha ao transmitir entregador do pedido #{$order->id}: " . $e->getMessage());
        }
    }

    public function updateDeliveryPayment(Order $order, array $data): void
    {
        $order->update([
            'payment_intent_status'  => $data['payment_intent_status'],
            'payment_intent_methods' => $data['payment_intent_methods'] ?? null,
        ]);

        try {
            broadcast(new OrderUpdated($order));
        } catch (\Throwable $e) {
            Log::error("🚨 [WebSockets] Falha ao transmitir status de pagamento do pedido #{$order->id}: " . $e->getMessage());
        }
    }

    public function getAvailableCouriers(): array
    {
        $settings = CompanySetting::getCached();

        $couriersQuery = User::with('employee:id,phone')
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['driver', 'motoboy', 'entregador']);
            })
            ->where('is_active', true);

        if ($settings && $settings->require_shift_attendance) {
            $couriersQuery->whereHas('attendedShifts', function ($q) {
                $q->where('cash_shifts.status', 'open')
                  ->where('shift_attendances.status', 'active')
                  ->where('shift_attendances.attendance_status', 'present');
            });
        }

        return $couriersQuery->get(['id', 'name', 'employee_id'])
            ->map(fn ($user) => [
                'id'          => $user->id,
                'name'        => $user->name,
                'phone'       => $user->employee ? $user->employee->phone : null,
                'employee_id' => $user->employee_id ?? $user->id,
            ])->toArray();
    }

    public function bulkCompleteDispatched(): array
    {
        // 🎯 Foca exclusivamente nos pedidos entregues pelo motoboy aguardando baixa
        $orders = Order::with(['payments', 'customer'])
            ->where('status', 'delivered')
            ->get();

        $activeShift = \App\Models\CashShift::where('status', 'open')->latest()->first();
        $processed = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            $now = now();
            $deliveredAt = $order->delivered_at ?? $now;

            // 1. Se já está pago (Pix, Cartão online, etc)
            if ($order->payment_status === 'paid' || $order->payment_intent_status === 'paid') {
                $order->update([
                    'status'       => 'closed',
                    'closed_at'    => $now,
                    'delivered_at' => $deliveredAt,
                ]);

                try {
                    broadcast(new OrderUpdated($order->fresh()));
                } catch (\Throwable $e) {}

                $processed++;
                continue;
            }

            // 2. Liquidar pagamentos prometidos na entrega (Dinheiro / Maquininha)
            $promises = $order->payment_intent_methods;
            if (is_string($promises)) {
                $promises = json_decode($promises, true);
            }

            if (empty($promises) || !is_array($promises)) {
                $skipped++;
                continue;
            }

            $orderTotal = (float) $order->total;
            $alreadyPaid = (float) $order->payments->sum('amount');
            $remaining = round($orderTotal - $alreadyPaid, 2);

            if ($remaining <= 0) {
                $order->update([
                    'payment_status'        => 'paid',
                    'payment_intent_status' => 'paid',
                    'status'                => 'closed',
                    'closed_at'             => $now,
                    'delivered_at'          => $deliveredAt,
                ]);

                try {
                    broadcast(new OrderUpdated($order->fresh()));
                } catch (\Throwable $e) {}

                $processed++;
                continue;
            }

            $promisedTotal = collect($promises)->sum(fn ($p) => (float) ($p['amount'] ?? 0));

            if (round($promisedTotal, 2) >= ($remaining - 0.05)) {
                DB::transaction(function () use ($order, $promises, $activeShift, $now, $deliveredAt) {
                    foreach ($promises as $promise) {
                        $amount = (float) ($promise['amount'] ?? 0);
                        if ($amount <= 0) continue;

                        $methodId = !empty($promise['method_id']) ? (int) $promise['method_id'] : null;
                        $methodName = $promise['method_name'] ?? 'Dinheiro';

                        $orderPayment = $order->payments()->create([
                            'payment_method_id' => $methodId,
                            'cash_shift_id'     => $activeShift?->id,
                            'cashier_id'        => auth()->id(),
                            'method'            => $methodName,
                            'amount'            => $amount,
                            'status'            => 'completed',
                            'is_paid'           => 1,
                        ]);

                        if ($activeShift) {
                            $isCash = str_contains(mb_strtolower($methodName), 'dinheiro');
                            \App\Models\Transaction::create([
                                'bank_account_id'   => $isCash ? 1 : null,
                                'cash_shift_id'     => $activeShift->id,
                                'order_id'          => $order->id,
                                'payment_id'        => $orderPayment->id,
                                'payment_method_id' => $methodId,
                                'type'              => 'in',
                                'category'          => 'order_sale',
                                'amount'            => $amount,
                                'description'       => "Pedido #{$order->id} ({$methodName})",
                                'operator_name'     => auth()->user()?->name ?? 'Sistema',
                                'transaction_date'  => $now,
                            ]);
                        }
                    }

                    $order->update([
                        'payment_status'        => 'paid',
                        'payment_intent_status' => 'paid',
                        'status'                => 'closed',
                        'closed_at'             => $now,
                        'delivered_at'          => $deliveredAt,
                    ]);
                });

                try {
                    broadcast(new OrderUpdated($order->fresh()));
                } catch (\Throwable $e) {}

                $processed++;
            } else {
                $skipped++;
            }
        }

        return [
            'processed' => $processed,
            'skipped'   => $skipped
        ];
    }
}
