<?php

namespace App\Services\Orders\Handlers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\CompanySetting;
use Exception;

class CounterOrderHandler
{
    public function resolveCustomer(array &$routing): array
    {
        $customerId = $routing["customer"]["id"] ?? ($routing["customer_id"] ?? null);
        $customerName = null;

        if (!empty($customerId) && str_starts_with((string) $customerId, 'temp_')) {
            $tempName = $routing["customer"]["name"] ?? ($routing["customer_name"] ?? 'Cliente Balcão');
            $tempPhone = preg_replace('/\D/', '', $routing["customer"]["phone"] ?? ($routing["customer_phone"] ?? ''));

            $customer = Customer::create([
                'name'  => $tempName,
                'phone' => !empty($tempPhone) ? $tempPhone : null,
                'cpf'   => !empty($routing["customer"]["cpf"]) ? preg_replace('/\D/', '', $routing["customer"]["cpf"]) : null,
            ]);

            $customerId = $customer->id;
            $customerName = $customer->name;
        } else {
            $candidate = $routing["customer"]["name"] ?? ($routing["identifier"] ?? ($routing["customer_name"] ?? null));
            $customerName = !empty($candidate) ? trim($candidate) : null;
        }

        if (empty($customerName) && empty($customerId)) {
            throw new Exception("Para pedidos de Balcão, é obrigatório identificar o cliente (Nome ou Cadastro).");
        }

        return [
            'id'   => is_numeric($customerId) ? (int) $customerId : null,
            'name' => $customerName
        ];
    }

    public function buildOrderData(array $payload, int $operatorId, string $source): array
    {
        $routing = $payload['routing'] ?? [];
        $financials = $payload['financials'] ?? [];
        $settings = CompanySetting::getCached() ?? CompanySetting::first();

        $customerData = $this->resolveCustomer($routing);

        $subtotal = (float) ($financials["subtotal"] ?? 0);
        $serviceFee = $settings ? $settings->calculateServiceFee($subtotal, 'counter') : 0.00;
        $discount = (float) ($financials["discount"] ?? 0);
        $finalTotal = round($subtotal + $serviceFee - $discount, 2);

        $changeTarget = $routing["change_target"] ?? ($routing["delivery_change_target"] ?? ($routing["cash_change_target"] ?? null));

        return [
            "customer_id"            => $customerData['id'],
            "customer_name"          => $customerData['name'],
            "customer_phone"         => $routing["customer_phone"] ?? ($routing["customer"]["phone"] ?? null),
            "coupon_id"              => $financials["coupon_id"] ?? null,
            "type"                   => "counter",
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
            "change_target"          => $changeTarget,
        ];
    }
}
