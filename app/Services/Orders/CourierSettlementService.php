<?php

namespace App\Services\Orders;

use App\Enums\CourierPaymentModel;
use App\Enums\EmployeeRole;
use App\Models\CashShift;
use App\Models\Employee;
use App\Models\Order;
use App\Models\ShiftAttendance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CourierSettlementService
{
    /**
     * Calcula o valor da taxa que o motoboy receberá pelo pedido.
     */
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

            default => (float) $courier->default_delivery_fee, // FIXED
        };
    }

    /**
     * Retorna os motoboys ativos no turno de caixa aberto.
     */
    public function getActiveCouriersOnShift(?CashShift $shift = null): Collection
    {
        $currentShift = $shift ?? CashShift::where('status', 'open')->latest('id')->first();
        if (!$currentShift) {
            return collect();
        }

        // Busca registros de presença ativos no turno atual cuja função seja entregador
        return Employee::whereHas('attendances', function ($q) use ($currentShift) {
            $q->where('cash_shift_id', $currentShift->id)
              ->whereNull('checked_out_at');
        })
        ->where(function ($q) {
            $q->where('role', EmployeeRole::COURIER->value ?? 'courier')
              ->orWhere('role', 'courier');
        })
        ->get();
    }

    /**
     * Tenta resolver o motoboy do pedido automaticamente.
     * Se houver apenas 1 motoboy ativo no turno, atribui e congela a taxa.
     */
    public function tryAutoAssign(Order $order): bool
    {
        if ($order->courier_id) {
            return true; // Já atribuído
        }

        $activeCouriers = $this->getActiveCouriersOnShift();

        if ($activeCouriers->count() === 1) {
            $this->assignCourier($order, $activeCouriers->first());
            return true;
        }

        // Se houver 0 ou mais de 1, permanece aguardando atribuição manual
        return false;
    }

    /**
     * Atribui manualmente ou via despacho o motoboy ao pedido e congela a taxa devida.
     */
    public function assignCourier(Order $order, Employee $courier): void
    {
        $fee = $this->calculateFee($courier, $order);

        $order->update([
            'courier_id'          => $courier->id,
            'courier_fee'         => $fee,
            'courier_assigned_at' => now(),
        ]);
    }

    /**
     * Gera o extrato de corridas de um motoboy para um turno ou intervalo de datas.
     */
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

        // Dinheiro recolhido na rua pelo motoboy (pedidos recebidos em 'cash')
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
            'net_cash_balance'     => $cashCollected - $finalPayable, // Se positivo, motoboy devolve ao caixa; se negativo, caixa paga ao motoboy
        ];
    }
}
