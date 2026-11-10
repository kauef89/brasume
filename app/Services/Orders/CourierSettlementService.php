<?php

namespace App\Services\Orders;

use App\Enums\CourierPaymentModel;
use App\Enums\EmployeeRole;
use App\Events\OrderUpdated;
use App\Models\CashShift;
use App\Models\Employee;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderReadyForDeliveryNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CourierSettlementService
{
    /**
     * Resolve de forma resiliente a instância de Employee a partir de User, Employee ou ID.
     */
    public static function resolveEmployee(User|Employee|int|null $subject): ?Employee
    {
        if (!$subject) {
            return null;
        }

        if ($subject instanceof Employee) {
            return $subject;
        }

        if ($subject instanceof User) {
            return $subject->employee
                ?? Employee::where('user_id', $subject->id)->first()
                ?? Employee::find($subject->id);
        }

        $id = (int) $subject;
        return Employee::find($id)
            ?? Employee::where('user_id', $id)->first()
            ?? User::find($id)?->employee;
    }

    /**
     * Retorna a lista unificada de Employees (motoboys) ativos no turno aberto.
     */
    public function getActiveCouriersOnShift(?CashShift $shift = null): Collection
    {
        $currentShift = $shift ?? CashShift::where('status', 'open')->latest('id')->first();
        if (!$currentShift) {
            return collect();
        }

        $roles = [EmployeeRole::COURIER->value, 'driver', 'motoboy'];

        $attendees = $currentShift->attendees()
            ->wherePivot('status', 'active')
            ->wherePivot('attendance_status', 'present')
            ->wherePivotNull('checked_out_at')
            ->where('users.is_active', true)
            ->with(['employee', 'roles'])
            ->get();

        $courierEmployees = collect();

        foreach ($attendees as $user) {
            $assignedRole = $user->pivot->assigned_role;
            $hasRole = in_array($assignedRole, $roles, true)
                || $user->roles->contains(fn ($r) => in_array($r->name, $roles, true))
                || ($user->employee && ($user->employee->is_courier || in_array($user->employee->role?->value ?? (string) $user->employee->role, $roles, true)));

            if ($hasRole) {
                $employee = self::resolveEmployee($user);
                if ($employee && !$courierEmployees->contains('id', $employee->id)) {
                    $employee->setRelation('user', $user);
                    $courierEmployees->push($employee);
                }
            }
        }

        return $courierEmployees;
    }

    /**
     * Regra 2: Se houver exatamente 1 motoboy ativo no turno, atribui o pedido a ele.
     */
    public function tryAutoAssign(Order $order, ?CashShift $shift = null): bool
    {
        if ($order->courier_id) {
            return true;
        }

        $activeCouriers = $this->getActiveCouriersOnShift($shift);

        if ($activeCouriers->count() === 1) {
            $this->assignCourier($order, $activeCouriers->first());
            return true;
        }

        return false;
    }

    /**
     * Regra 1: Quando o motoboy entra no turno e é o único, assume os pedidos pendentes.
     */
    public function autoAssignPendingOrdersToSoleCourier(Employee $courier, ?CashShift $shift = null): int
    {
        $activeCouriers = $this->getActiveCouriersOnShift($shift);
        if ($activeCouriers->count() !== 1 || $activeCouriers->first()->id !== $courier->id) {
            return 0;
        }

        $orders = Order::where('type', 'delivery')
            ->whereNull('courier_id')
            ->whereIn('status', ['pending', 'preparing', 'ready'])
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $this->assignCourier($order, $courier);
            $count++;
        }

        return $count;
    }

    /**
     * Atribui o motoboy, calcula a taxa devida, sincroniza user_id e notifica.
     */
    public function assignCourier(Order $order, Employee $courier, ?float $distanceKm = null): void
    {
        if ($distanceKm !== null && $distanceKm >= 0) {
            $order->delivery_distance_km = $distanceKm;
        }

        $fee = $this->calculateFee($courier, $order);
        $userId = $courier->user_id ?? $courier->user?->id;

        $order->update([
            'courier_id'          => $courier->id,
            'driver_id'           => $userId,
            'courier_fee'         => $fee,
            'courier_assigned_at' => now(),
        ]);

        $user = $courier->user ?? ($userId ? User::find($userId) : null);
        if ($user) {
            try {
                $user->notify(new OrderReadyForDeliveryNotification($order));
            } catch (\Throwable $e) {
                Log::warning("⚠️ [WebPush] Falha ao notificar motoboy #{$courier->id}: " . $e->getMessage());
            }
        }

        try {
            broadcast(new OrderUpdated($order->fresh()));
        } catch (\Throwable $e) {}
    }

    public function calculateFee(Employee $courier, Order $order): float
    {
        $model = $courier->courier_payment_model instanceof CourierPaymentModel
            ? $courier->courier_payment_model
            : CourierPaymentModel::tryFrom((string) $courier->courier_payment_model) ?? CourierPaymentModel::FIXED;

        return match ($model) {
            CourierPaymentModel::DISTANCE => round(((float) $order->delivery_distance_km) * ((float) $courier->fee_per_km), 2),
            CourierPaymentModel::NEIGHBORHOOD => (float) (
                $order->neighborhood?->courier_fee
                ?? $courier->default_delivery_fee
                ?? 0.00
            ),
            default => (float) $courier->default_delivery_fee,
        };
    }

    public function getCourierStatement(Employee $courier, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $start = $startDate ?: now()->startOfDay();
        $end = $endDate ?: now()->endOfDay();

        $deliveries = Order::with(['customer', 'neighborhood'])
            ->where('courier_id', $courier->id)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('id', 'asc')
            ->get();

        $totalDeliveriesFee = (float) $deliveries->sum('courier_fee');
        $dailyFee = (float) $courier->daily_fee;
        $minimumGuaranteed = (float) $courier->minimum_guaranteed;

        $grossTotal = $dailyFee + $totalDeliveriesFee;
        $finalPayable = max($grossTotal, $minimumGuaranteed);

        $cashCollected = (float) $deliveries->filter(function ($order) {
            return $order->payment_method === 'cash';
        })->sum('total_amount');

        return [
            'courier'              => $courier,
            'deliveries_count'     => $deliveries->count(),
            'deliveries'           => $deliveries,
            'daily_fee'            => $dailyFee,
            'total_deliveries_fee' => $totalDeliveriesFee,
            'gross_total'          => $grossTotal,
            'minimum_guaranteed'   => $minimumGuaranteed,
            'final_payable'        => $finalPayable,
            'cash_collected'       => $cashCollected,
            'net_cash_balance'     => $cashCollected - $finalPayable,
        ];
    }
}
