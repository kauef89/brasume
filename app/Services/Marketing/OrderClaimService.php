<?php

namespace App\Services\Marketing;

use App\Models\Order;
use App\Models\Customer;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class OrderClaimService
{
    /**
     * Gera ou recupera um claim_hash único para pedidos não identificados.
     */
    public function generateClaimHashForOrder(Order $order): string
    {
        if ($order->claim_hash && $order->claim_expires_at && now()->lessThan($order->claim_expires_at)) {
            return $order->claim_hash;
        }

        $hash = 'MESA-' . strtoupper(Str::random(6));

        $order->updateQuietly([
            'claim_hash'       => $hash,
            'claim_expires_at' => now()->addHours(48), // Janela de 48h
        ]);

        return $hash;
    }

    /**
     * Calcula quanto o pedido renderá de pontos/cashback para o cliente.
     */
    public function calculateEstimatedRewards(Order $order, ?CompanySetting $settings = null): array
    {
        $settings = $settings ?? CompanySetting::getCached();
        if (!$settings || !$settings->loyalty_is_active) {
            return ['points' => 0, 'cashback_value' => 0.00];
        }

        $subtotal = (float) $order->subtotal;
        $multiplier = (float) ($settings->loyalty_points_multiplier ?? 1);
        $points = (int) round($subtotal * $multiplier);

        $conversionRate = (float) ($settings->loyalty_cashback_conversion_rate ?? 5.00);
        $cashbackValue = round(($points / 100) * $conversionRate, 2);

        return [
            'points'         => $points,
            'cashback_value' => $cashbackValue,
        ];
    }

    /**
     * Processa a reivindicação do pedido de forma atômica.
     */
    public function claimOrder(string $hash, Customer $customer): Order
    {
        return DB::transaction(function () use ($hash, $customer) {
            /** @var Order $order */
            $order = Order::where('claim_hash', strtoupper(trim($hash)))
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new Exception('Comprovante de resgate inválido ou não encontrado.');
            }

            if ($order->claimed_at) {
                throw new Exception('Este comprovante já foi resgatado anteriormente.');
            }

            if ($order->claim_expires_at && now()->greaterThan($order->claim_expires_at)) {
                throw new Exception('O prazo de 48 horas para resgatar este pedido expirou.');
            }

            if (!in_array($order->status, ['closed', 'completed', 'delivered']) && $order->payment_status !== 'paid') {
                throw new Exception('O pedido ainda não foi finalizado pelo estabelecimento.');
            }

            if ($order->customer_id && $order->customer_id !== $customer->id) {
                throw new Exception('Este pedido já possui outro cliente vinculado.');
            }

            // 1. Calcula os pontos devidos
            $settings = CompanySetting::getCached();
            $rewards = $this->calculateEstimatedRewards($order, $settings);

            // 2. Atualiza a comanda
            $order->update([
                'customer_id'           => $customer->id,
                'customer_name'         => $customer->name,
                'customer_phone'        => $customer->phone,
                'loyalty_points_earned' => $rewards['points'],
                'cashback_earned'       => $rewards['cashback_value'],
                'claimed_at'            => now(),
            ]);

            // 3. Credita no saldo do cliente
            if ($rewards['points'] > 0) {
                $customer->increment('loyalty_points', $rewards['points']);
            }

            return $order;
        });
    }
}
