<?php

namespace App\Services\Operations;

use App\Models\Order;
use App\Models\CompanySetting;

class OperationsService
{
    /**
     * Mapeia e estrutura todos os pedidos ativos (Salão, Balcão, Delivery) para o Kanban
     */
    public function getActiveOrdersDashboard(): array
    {
        $settings = CompanySetting::first();

        // Puxa apenas pedidos que não foram finalizados ou cancelados
        $orders = Order::with(["items", "customer.addresses"])
            ->whereNotIn("status", ["closed", "cancelled"])
            ->orderBy("created_at", "asc")
            ->get();

        $mappedOrders = $orders->map(function ($order) {
            // Formata o endereço completo de entrega caso exista
            $fullAddress = null;
            if ($order->type === "delivery" && $order->address_id) {
                $addr = $order->address;
                if ($addr) {
                    $fullAddress = trim(
                        "{$addr->street_name}, {$addr->number} - {$addr->neighborhood_name} " .
                            ($addr->complement ? "({$addr->complement})" : ""),
                    );
                }
            }

            return [
                "id" => $order->id,
                "customer_name" => $order->customer_name,
                "table_number" => $order->table_number,
                "type" => $order->type, // 'table', 'counter', 'delivery'
                "status" => $order->status, // 'pending', 'preparing', 'ready', 'delivered'
                "payment_status" => $order->payment_status,
                "delivery_payment_status" => $order->delivery_payment_status,
                "delivery_payment_methods" => is_string(
                    $order->delivery_payment_methods,
                )
                    ? json_decode($order->delivery_payment_methods)
                    : $order->delivery_payment_methods,
                "total" => (float) $order->total,
                "time" => $order->created_at->format("H:i"),
                "minutes_active" => $order->created_at->diffInMinutes(now()),
                "address" => $fullAddress,
                "phone" => $order->customer->phone ?? null,
                "items" => $order->items
                    ->filter(fn($i) => !$i->parent_item_id)
                    ->map(
                        fn($i) => [
                            "id" => $i->id,
                            "name" => $i->product_name,
                            "qty" => $i->quantity,
                        ],
                    )
                    ->values(),
            ];
        });

        return [
            "initialOrders" => $mappedOrders,
            "companySettings" => [
                "has_service_tax" =>
                    (bool) ($settings->has_service_tax ?? false),
                "service_tax_percentage" =>
                    (float) ($settings->service_tax_percentage ?? 10.0),
            ],
        ];
    }

    public function updateOrderStatus(Order $order, string $newStatus): void
    {
        $order->update(["status" => $newStatus]);
    }
}
