<?php

namespace App\Services\Orders\Handlers;

use App\Models\Order;
use App\Models\User;
use App\Models\Customer;
use App\Models\CashShift;
use App\Models\CompanySetting;
use App\Services\Orders\OrderAddressResolver;

class DeliveryOrderHandler
{
    public function __construct(protected OrderAddressResolver $addressResolver) {}

    /**
     * Resolve o motoboy do pedido:
     * - Retorna o ID se houver EXATAMENTE 1 motorista ativo e presente no turno aberto.
     * - Retorna NULL se não houver nenhum motorista com presença confirmada ou se houver mais de um.
     */
    public function resolveCourier(?CashShift $shift = null): ?int
    {
        $shift = $shift ?? CashShift::where('status', 'open')->latest('id')->first();

        if (!$shift) {
            return null;
        }

        $roles = ['driver', 'motoboy', 'entregador'];

        $activeDrivers = $shift->attendees()
            ->wherePivot('status', 'active')
            ->wherePivot('attendance_status', 'present')
            ->where(function ($query) use ($roles) {
                $query->whereIn('shift_attendances.assigned_role', $roles)
                      ->orWhereHas('roles', function ($q) use ($roles) {
                          $q->whereIn('name', $roles);
                      });
            })
            ->where('users.is_active', true)
            ->get();

        return $activeDrivers->count() === 1 ? (int) $activeDrivers->first()->id : null;
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

        // Respeita seleção manual do operador se enviada; caso contrário, busca o único motoboy do turno
        $explicitDriverId = !empty($routing["driver_id"]) ? (int) $routing["driver_id"] : (!empty($payload["driver_id"]) ? (int) $payload["driver_id"] : null);
        $resolvedDriverId = $explicitDriverId ?: $this->resolveCourier();

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
            "driver_id"              => $resolvedDriverId,
            "payment_intent_status"  => $routing["payment_intent_status"] ?? 'pending_choice',
            "payment_intent_methods" => $routing["payment_intent_methods"] ?? null,
            "change_target"          => $changeTarget,
        ];
    }
}
