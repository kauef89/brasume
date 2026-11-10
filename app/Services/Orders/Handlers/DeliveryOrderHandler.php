<?php

namespace App\Services\Orders\Handlers;

use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Order;
use App\Services\Orders\CourierSettlementService;
use App\Services\Orders\OrderAddressResolver;

class DeliveryOrderHandler
{
    public function __construct(
        protected OrderAddressResolver $addressResolver,
        protected CourierSettlementService $courierSettlementService
    ) {}

    public function resolveCourier(?CashShift $shift = null): ?int
    {
        $activeCouriers = $this->courierSettlementService->getActiveCouriersOnShift($shift);
        return $activeCouriers->count() === 1 ? (int) $activeCouriers->first()->id : null;
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

        // Resolução segura de entregador (prioriza escolha manual; caso contrário, aplica Regra 2)
        $rawDriverId = !empty($routing["courier_id"])
            ? (int) $routing["courier_id"]
            : (!empty($routing["driver_id"]) ? (int) $routing["driver_id"] : null);

        $resolvedCourier = null;
        if ($rawDriverId) {
            $resolvedCourier = CourierSettlementService::resolveEmployee($rawDriverId);
        }

        if (!$resolvedCourier) {
            $soleCourierId = $this->resolveCourier();
            $resolvedCourier = $soleCourierId ? Employee::find($soleCourierId) : null;
        }

        $courierFee = 0.00;
        if ($resolvedCourier) {
            $courierFee = $this->courierSettlementService->calculateFee($resolvedCourier, new Order([
                'delivery_distance_km' => $routing['delivery_distance_km'] ?? 0,
                'address_id'           => $resolvedAddress['address_id'],
            ]));
        }

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
            "employee_id"            => !empty($routing["employee_id"]) ? (int) $routing["employee_id"] : null,
            "waiter_id"              => null,
            "cashier_id"             => in_array($source, [Order::SOURCE_POS_APP, Order::SOURCE_CASHIER], true) ? $operatorId : null,
            "courier_id"             => $resolvedCourier?->id,
            "driver_id"              => $resolvedCourier?->user_id ?? $resolvedCourier?->user?->id,
            "courier_fee"            => $courierFee,
            "courier_assigned_at"    => $resolvedCourier ? now() : null,
            "payment_intent_status"  => $routing["payment_intent_status"] ?? 'pending_choice',
            "payment_intent_methods" => $routing["payment_intent_methods"] ?? null,
            "change_target"          => $changeTarget,
        ];
    }
}
