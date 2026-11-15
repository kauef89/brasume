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
            if ($subject->employee_id) {
                $emp = Employee::find($subject->employee_id);
                if ($emp) return $emp;
            }
            return $subject->employee
                ?? (\Illuminate\Support\Facades\Schema::hasColumn('employees', 'user_id')
                    ? Employee::where('user_id', $subject->id)->first()
                    : null);
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

        // Fallback: se não há motoboy marcado em shift_attendances, verifica quem foi escalado hoje (ex: adicionado após abertura do turno)
        if ($courierEmployees->isEmpty() && \Illuminate\Support\Facades\Schema::hasTable('employee_schedules')) {
            $todaySchedules = \App\Models\EmployeeSchedule::with(['employee.user'])
                ->whereDate('date', now()->toDateString())
                ->where('is_day_off', false)
                ->where('status', '!=', 'cancelled')
                ->get();

            foreach ($todaySchedules as $sched) {
                $emp = $sched->employee;
                if (!$emp || !$emp->is_active) continue;

                $empRole = $emp->role instanceof \BackedEnum ? $emp->role->value : (string) $emp->role;
                $secRoles = is_array($emp->secondary_roles) ? $emp->secondary_roles : [];
                $schedRole = (string) ($sched->assigned_role ?? '');

                $isCourier = (bool) $emp->is_courier
                    || in_array($empRole, $roles, true)
                    || in_array($schedRole, $roles, true)
                    || !empty(array_intersect($secRoles, $roles));

                if ($isCourier && !$courierEmployees->contains('id', $emp->id)) {
                    $u = $emp->user ?? User::where('employee_id', $emp->id)->first();
                    if ($u) {
                        $emp->setRelation('user', $u);
                    }
                    $courierEmployees->push($emp);
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

        $deliveries = Order::with(['customer', 'deliveryAddress.neighborhood', 'payments.paymentMethod'])
            ->where('courier_id', $courier->id)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->orderBy('id', 'asc')
            ->get();

        $modelEnum = $courier->courier_payment_model instanceof CourierPaymentModel
            ? $courier->courier_payment_model
            : (CourierPaymentModel::tryFrom((string) $courier->courier_payment_model) ?? CourierPaymentModel::FIXED);

        $totalDeliveriesFee = 0.0;
        $cashCollected = 0.0;
        $deliveriesList = [];

        foreach ($deliveries as $order) {
            $fee = (float) ($order->courier_fee ?? 0);
            if ($fee <= 0 && (float) $courier->default_delivery_fee > 0) {
                $fee = $this->calculateFee($courier, $order);
            }
            $totalDeliveriesFee += $fee;

            // Apura recebimento em espécie (dinheiro) na entrega
            $orderCash = 0.0;
            if ($order->payments && $order->payments->isNotEmpty()) {
                foreach ($order->payments as $pay) {
                    $engine = strtolower((string) ($pay->paymentMethod?->engine_type ?? $pay->payment_method_name ?? ''));
                    if (in_array($engine, ['money', 'cash', 'dinheiro'], true) || str_contains($engine, 'dinheiro')) {
                        $orderCash += (float) $pay->amount;
                    }
                }
            } else {
                $pmName = strtolower((string) ($order->payment_method_name ?? $order->payment_method ?? ''));
                if (in_array($pmName, ['money', 'cash', 'dinheiro'], true) || str_contains($pmName, 'dinheiro')) {
                    $orderCash = (float) ($order->total ?? 0);
                }
            }

            $cashCollected += $orderCash;

            $addr = is_array($order->address) ? $order->address : [];
            $neighborhoodName = $order->deliveryAddress?->neighborhood?->name
                ?? ($addr['neighborhood'] ?? null)
                ?? 'Entrega Local';

            $deliveriesList[] = [
                'id'           => $order->id,
                'time'         => $order->created_at ? $order->created_at->format('H:i') : '',
                'customer'     => $order->customer_name ?: ($order->customer?->name ?? 'Cliente'),
                'neighborhood' => $neighborhoodName,
                'distance_km'  => (float) ($order->delivery_distance_km ?? 0),
                'courier_fee'  => round($fee, 2),
                'order_total'  => round((float) ($order->total ?? 0), 2),
                'is_cash'      => $orderCash > 0.009,
                'cash_amount'  => round($orderCash, 2),
            ];
        }

        $totalDeliveriesFee = round($totalDeliveriesFee, 2);
        $cashCollected = round($cashCollected, 2);
        $dailyFee = round((float) ($courier->daily_fee ?? 0), 2);
        $minimumGuaranteed = round((float) ($courier->minimum_guaranteed ?? 0), 2);

        $grossTotal = round($dailyFee + $totalDeliveriesFee, 2);
        $finalPayable = max($grossTotal, $minimumGuaranteed);
        $minimumApplied = $minimumGuaranteed > 0 && $minimumGuaranteed > $grossTotal;
        $minimumComplement = $minimumApplied ? round($minimumGuaranteed - $grossTotal, 2) : 0.0;

        return [
            'courier'              => $courier,
            'payment_model'        => $modelEnum->value,
            'payment_model_label'  => $modelEnum->getLabel(),
            'default_delivery_fee' => round((float) ($courier->default_delivery_fee ?? 0), 2),
            'fee_per_km'           => round((float) ($courier->fee_per_km ?? 0), 2),
            'deliveries_count'     => $deliveries->count(),
            'deliveries'           => $deliveries,
            'deliveries_list'      => $deliveriesList,
            'daily_fee'            => $dailyFee,
            'total_deliveries_fee' => $totalDeliveriesFee,
            'gross_total'          => $grossTotal,
            'minimum_guaranteed'   => $minimumGuaranteed,
            'minimum_applied'      => $minimumApplied,
            'minimum_complement'   => $minimumComplement,
            'final_payable'        => $finalPayable,
            'cash_collected'       => $cashCollected,
            'net_cash_balance'     => round($cashCollected - $finalPayable, 2),
        ];
    }
}
