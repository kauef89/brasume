<?php

namespace App\Services\Orders\Handlers;

use App\Models\Order;
use App\Models\DiningTable;
use App\Models\CompanySetting;
use Exception;

class TableOrderHandler
{
    public function resolveTableId(array $routing): int
    {
        $tableId = $routing["table"]["id"] ?? ($routing["dining_table_id"] ?? ($routing["table_id"] ?? null));
        $tableName = $routing["table"]["name"] ?? ($routing["table_number"] ?? null);

        // Se veio apenas o nome da mesa (ex: "13")
        if (empty($tableId) && !empty($tableName)) {
            $tableId = DiningTable::where('name', (string) $tableName)->value('id');
        }

        // Validação defensiva: se o ID não existir, tenta pelo nome
        if ($tableId && !DiningTable::where('id', $tableId)->exists() && !empty($tableName)) {
            $tableId = DiningTable::where('name', (string) $tableName)->value('id');
        }

        if (empty($tableId)) {
            throw new Exception("A mesa (ID) é obrigatória para pedidos de salão. Nenhuma mesa foi identificada.");
        }

        return (int) $tableId;
    }

    public function findActiveTableOrder(int $tableId, bool $isSubaccount = false): ?Order
    {
        if ($isSubaccount) return null;

        return Order::where('dining_table_id', $tableId)
            ->whereNotIn('status', ['closed', 'cancelled', 'completed', 'delivered', 'merged'])
            ->where('payment_status', '!=', 'paid')
            ->latest('id')
            ->first();
    }

    public function buildOrderData(array $payload, int $tableId, int $operatorId, string $source): array
    {
        $routing = $payload['routing'] ?? [];
        $financials = $payload['financials'] ?? [];
        $settings = CompanySetting::getCached() ?? CompanySetting::first();

        // Busca a entidade física canônica para garantir que nome e número correspondam
        $table = DiningTable::findOrFail($tableId);
        $officialTableName = $table->name;

        $subtotal = (float) ($financials["subtotal"] ?? 0);
        $serviceFee = $settings ? $settings->calculateServiceFee($subtotal, 'table') : 0.00;
        $discount = (float) ($financials["discount"] ?? 0);
        $finalTotal = round($subtotal + $serviceFee - $discount, 2);

        // Se o operador não digitou um nome específico para a conta/cliente, adota o nome canônico
        $customerName = !empty($routing["identifier"])
            ? trim($routing["identifier"])
            : (!empty($routing["customer"]["name"]) ? trim($routing["customer"]["name"]) : "Mesa " . $officialTableName);

        return [
            "customer_id"            => $routing["customer"]["id"] ?? ($routing["customer_id"] ?? null),
            "dining_table_id"        => $table->id,
            "table_number"           => $officialTableName,
            "customer_name"          => $customerName,
            "customer_phone"         => $routing["customer_phone"] ?? ($routing["customer"]["phone"] ?? null),
            "coupon_id"              => $financials["coupon_id"] ?? null,
            "type"                   => "table",
            "source"                 => $source,
            "status"                 => "pending",
            "payment_status"         => "pending",
            "subtotal"               => $subtotal,
            "discount"               => $discount,
            "delivery_fee"           => 0.00,
            "service_fee"            => $serviceFee,
            "total"                  => $finalTotal,
            "employee_id"            => $operatorId,
            "cashier_id"             => in_array($source, [Order::SOURCE_POS_APP, Order::SOURCE_CASHIER], true) ? $operatorId : null,
            "payment_intent_status"  => $routing["payment_intent_status"] ?? 'pending',
            "payment_intent_methods" => $routing["payment_intent_methods"] ?? null,
        ];
    }
}
