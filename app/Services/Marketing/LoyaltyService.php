<?php

namespace App\Services\Marketing;

use App\Models\Customer;
use App\Models\Order;
use App\Models\CompanySetting;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Exception;

class LoyaltyService
{
    public function validateCashback(Customer $customer, float $orderSubtotal, float $requestedCashback): float
    {
        if ($requestedCashback <= 0) return 0.0;

        $settings = CompanySetting::getCached();

        if (!$settings || !$settings->loyalty_is_active || $settings->loyalty_reward_type !== 'cashback') {
            throw new Exception("O programa de cashback não está ativo no momento.");
        }

        if ($customer->loyalty_points_expires_at && Carbon::parse($customer->loyalty_points_expires_at)->isPast()) {
            $customer->update([
                'loyalty_points' => 0,
                'loyalty_points_expires_at' => null,
            ]);
            throw new Exception("Seu saldo de cashback expirou e não está mais disponível.");
        }

        $minRedeem = (float) ($settings->loyalty_min_redeem_value ?? 0);
        if ($orderSubtotal < $minRedeem) {
            throw new Exception("O subtotal do pedido não atingiu o mínimo de R$ " . number_format($minRedeem, 2, ',', '.') . " para resgate.");
        }

        $maxRedeemVal = (float) ($settings->loyalty_max_redeem_value ?? 0);
        $maxRedeemType = $settings->loyalty_max_redeem_type ?? 'fixed';
        $maxCapLimit = $orderSubtotal;

        if ($maxRedeemVal > 0) {
            $maxCapLimit = ($maxRedeemType === 'percentage')
                ? round($orderSubtotal * ($maxRedeemVal / 100), 2)
                : $maxRedeemVal;
        }

        $conversionRate = max(0.01, (float) ($settings->loyalty_cashback_conversion_rate ?? 5));
        $maxAvailableInReais = ($customer->loyalty_points / 100) * $conversionRate;

        return min($requestedCashback, $maxAvailableInReais, $orderSubtotal, $maxCapLimit);
    }

    public function consumeCashback(Order $order, Customer $customer, float $cashbackUsed): void
    {
        if ($cashbackUsed <= 0) return;

        $settings = CompanySetting::getCached();
        $conversionRate = max(0.01, (float) ($settings->loyalty_cashback_conversion_rate ?? 5));

        $pointsToDeduct = (int) ceil(($cashbackUsed / $conversionRate) * 100);
        $customer->loyalty_points = max(0, $customer->loyalty_points - $pointsToDeduct);

        if ($customer->loyalty_points === 0) {
            $customer->loyalty_points_expires_at = null;
        }

        $customer->save();

        $hasCashbackPayment = $order->payments()->where('method', 'like', '%Cashback%')->exists();

        if (!$hasCashbackPayment) {
            $paymentMethod = PaymentMethod::where('engine_type', 'internal_loyalty')->first();

            $order->payments()->create([
                'payment_method_id' => $paymentMethod?->id,
                'amount'            => $cashbackUsed,
                'method'            => 'Cashback',
            ]);
        }
    }

    public function earnPoints(Order $order, Customer $customer): void
    {
        $settings = CompanySetting::getCached();
        if (!$settings || !$settings->loyalty_is_active) return;

        $cashbackUsed = (float) (
            $order->cashback_used > 0
                ? $order->cashback_used
                : $order->payments()->where('method', 'like', '%Cashback%')->sum('amount')
        );
        $couponDiscount = (float) ($order->discount ?? 0);

        // Pontua estritamente sobre dinheiro real gasto (sem cobrir cupom nem cashback já utilizado)
        $netMoneyPaid = max(0, (float) $order->subtotal - $couponDiscount - $cashbackUsed);

        if ($netMoneyPaid <= 0) {
            return;
        }

        $pointsEarned = 0;

        if ($settings->loyalty_accumulation_rule === 'cart_value') {
            $multiplier = (float) ($settings->loyalty_points_multiplier ?? 1);
            $pointsEarned = (int) floor($netMoneyPaid * $multiplier);
        } elseif ($settings->loyalty_accumulation_rule === 'product_qty_all') {
            $multiplier = (float) ($settings->loyalty_points_multiplier ?? 1);
            $totalQty = (int) $order->items()->sum('quantity');
            $pointsEarned = (int) floor($totalQty * $multiplier);
        }

        if ($pointsEarned > 0) {
            $customer->loyalty_points += $pointsEarned;

            $expiryDays = (int) ($settings->loyalty_points_expiry_days ?? 0);
            $customer->loyalty_points_expires_at = $expiryDays > 0
                ? Carbon::now()->addDays($expiryDays)
                : null;

            $customer->save();

            $cashbackEarned = null;
            if ($settings->loyalty_reward_type === 'cashback') {
                $conversionRate = max(0.01, (float) ($settings->loyalty_cashback_conversion_rate ?? 5));
                $calculatedValue = round(($pointsEarned / 100) * $conversionRate, 2);
                if ($calculatedValue > 0) {
                    $cashbackEarned = $calculatedValue;
                }
            }

            $order->update([
                'loyalty_points_earned' => $pointsEarned,
            ]);

            $order->cashback_earned = $cashbackEarned;
        }
    }

    public function refundOrderCashback(Order $order): void
    {
        if (!$order->customer) return;

        // 🚀 1. Revoga os pontos ganhos neste pedido cancelado
        if ((int) ($order->loyalty_points_earned ?? 0) > 0) {
            $pointsToRevoke = (int) $order->loyalty_points_earned;
            $order->customer->loyalty_points = max(0, (int) $order->customer->loyalty_points - $pointsToRevoke);
            $order->customer->save();
            $order->update(['loyalty_points_earned' => 0]);
        }

        // 🚀 2. Restaura os pontos gastos via Cashback como forma de pagamento
        $loyaltyPayment = $order->payments()
            ->where(function ($query) {
                $query->whereHas('paymentMethod', function ($q) {
                    $q->where('engine_type', 'internal_loyalty');
                })->orWhere('method', 'like', '%Cashback%');
            })->first();

        if (!$loyaltyPayment || $loyaltyPayment->amount <= 0) return;

        $amountRefunded = (float) $loyaltyPayment->amount;
        $settings = CompanySetting::getCached();
        $conversionRate = max(0.01, (float) ($settings->loyalty_cashback_conversion_rate ?? 5));

        $pointsToRefund = (int) ceil(($amountRefunded / $conversionRate) * 100);
        $order->customer->increment('loyalty_points', $pointsToRefund);

        $expiryDays = (int) ($settings->loyalty_points_expiry_days ?? 0);
        if ($expiryDays > 0 && (!$order->customer->loyalty_points_expires_at || Carbon::parse($order->customer->loyalty_points_expires_at)->isPast())) {
            $order->customer->update([
                'loyalty_points_expires_at' => Carbon::now()->addDays($expiryDays)
            ]);
        }
    }
}
