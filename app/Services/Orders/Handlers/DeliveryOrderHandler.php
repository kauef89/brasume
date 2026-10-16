<?php

namespace App\Services\Orders\Handlers;

use App\Models\Order;
use App\Models\User;
use App\Models\Customer;
use App\Models\CompanySetting;
use App\Services\Orders\OrderAddressResolver;

class DeliveryOrderHandler
{
    public function __construct(protected OrderAddressResolver $addressResolver) {}

    public function resolveCourier(): ?int
    {
        $activeCouriers = User::whereHas('roles', function($q) {
            $q->whereIn('name', ['driver', 'motoboy', 'entregador']);
        })
        ->whereHas('attendedShifts', function($q) {
            $q->where('cash_shifts.status', 'open');
        })
        ->where('is_active', true)
        ->get();

        return $activeCouriers->count() === 1 ? $activeCouriers->first()->id : null;
    }

    public function buildOrderData(array $payload, int $operatorId, string $source): array
    {
        $routing = $payload['routing'] ?? [];
        $financials = $payload['financials'] ?? [];
        $settings = CompanySetting::getCached() ?? CompanySetting::first();

        $customerId = $routing["customer"]["id"] ?? ($routing["customer_id"] ?? null);
        $customerName = $routing["customer"]["name"] ?? ($routing["customer_name"] ?? 'Cliente Delivery');

        if (!empty($customerId) && str_starts_with((string) $customerId, 'temp_')) {
            $tempPhone = preg_replace('/\D/', '', $routing["customer"]["phone"] ?? ($routing["customer_phone"] ?? ''));
            $created = Customer::create([
                'name'  => $customerName,
                'phone' => !empty($tempPhone) ? $tempPhone : null,
                'cpf'   => !empty($routing["customer"]["cpf"]) ? preg_replace('/\D/', '', $routing["customer"]["cpf"]) : null,
            ]);
            $customerId = $created->id;
        }

        $deliveryFee = (float) ($financials["deliveryFee"] ?? 0);
        $resolvedAddress = $this->addressResolver->resolve(
            $routing,
            is_numeric($customerId) ? (int) $customerId : null,
            $deliveryFee
        );

        $subtotal = (float) ($financials["subtotal"] ?? 0);
        $serviceFee = $settings ? $settings->calculateServiceFee($subtotal, 'delivery') : 0.00;
        $discount = (float) ($financials["discount"] ?? 0);
        $finalTotal = round($subtotal + $deliveryFee + $serviceFee - $discount, 2);

        $changeTarget = $routing["change_target"] ?? ($routing["delivery_change_target"] ?? ($routing["cash_change_target"] ?? null));

        return [
            "customer_id"            => is_numeric($customerId) ? (int) $customerId : null,
            "address_id"             => $resolvedAddress['address_id'],
            "address_snapshot"       => $resolvedAddress['snapshot'],
            "address"                => $resolvedAddress['snapshot'],
            "customer_name"          => $customerName,
            "customer_phone"         => $routing["customer_phone"] ?? ($routing["customer"]["phone"] ?? null),
            "coupon_id"              => $financials["coupon_id"] ?? null,
            "type"                   => "delivery",
            "source"                 => $source,
            "status"                 => "pending",
            "payment_status"         => "pending",
            "subtotal"               => $subtotal,
            "discount"               => $discount,
            "delivery_fee"           => $deliveryFee,
            "service_fee"            => $serviceFee,
            "total"                  => $finalTotal,
            "employee_id"            => $operatorId,
            "cashier_id"             => in_array($source, [Order::SOURCE_POS_APP, Order::SOURCE_CASHIER], true) ? $operatorId : null,
            "driver_id"              => $this->resolveCourier(),
            "payment_intent_status"  => $routing["payment_intent_status"] ?? 'pending_choice',
            "payment_intent_methods" => $routing["payment_intent_methods"] ?? null,
            "change_target"          => $changeTarget,
        ];
    }
}
