<?php

namespace App\Services\Orders\Handlers;

use App\Models\Order;
use App\Models\CompanySetting;
use App\Services\Orders\OrderItemProcessor;
use App\Services\RestaurantOps\KitchenDispatchService;
use App\Events\OrderUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderAppendHandler
{
    public function __construct(
        protected OrderItemProcessor $itemProcessor,
        protected KitchenDispatchService $kitchenDispatchService
    ) {}

    public function canAppend(?int $orderId): bool
    {
        if (empty($orderId)) return false;

        $order = Order::find($orderId);
        if (!$order) return false;

        if ($order->type === 'table' && $order->payment_status === 'paid') {
            return false;
        }

        return !in_array($order->status, ['closed', 'cancelled', 'completed', 'merged'], true);
    }

    public function handle(int $orderId, array $payload): Order
    {
        $order = DB::transaction(function () use ($orderId, $payload) {
            $order = Order::lockForUpdate()->findOrFail($orderId);

            if (in_array($order->status, ['closed', 'cancelled', 'completed', 'merged'], true)) {
                throw new \Exception("Ação recusada: O Pedido #{$order->id} já se encontra encerrado ou cancelado.");
            }

            if ($order->type === 'table' && $order->payment_status === 'paid') {
                throw new \Exception("Ação recusada: A comanda da Mesa #{$order->table_number} já foi quitada. Abra uma nova conta.");
            }

            $financials = $payload['financials'] ?? [];
            $routing    = $payload['routing'] ?? [];

            $addedSubtotal = (float) ($financials['subtotal'] ?? 0);
            $addedDiscount = (float) ($financials['discount'] ?? 0);

            $settings = CompanySetting::getCached() ?? CompanySetting::first();
            $addedServiceFee = $settings ? $settings->calculateServiceFee($addedSubtotal, $order->type) : 0.00;

            foreach ($payload['items'] as $item) {
                $this->itemProcessor->process($order, $item);
            }

            $order->subtotal += $addedSubtotal;
            $order->discount += $addedDiscount;
            $order->service_fee += $addedServiceFee;
            $order->total += round($addedSubtotal + $addedServiceFee - $addedDiscount, 2);

            if (method_exists($order, 'recalculatePaymentStatus')) {
                $order->recalculatePaymentStatus();
            } else {
                $totalPaid = (float) $order->payments()->sum('amount');
                if ($totalPaid <= 0) {
                    $order->payment_status = 'pending';
                } elseif ($totalPaid < ((float) $order->total - 0.01)) {
                    $order->payment_status = 'partial';
                } else {
                    $order->payment_status = 'paid';
                }
            }

            // 🚀 Chama o Recalculo com as regras refinadas
            $this->recalculatePaymentIntents($order, $routing);
            $order->save();

            return $order;
        });

        $isBankApiPending = ($payload['routing']['payment_engine'] ?? null) === 'bank_api'
            || ($order->payment_intent_status === 'pix_waiting');

        if (!$isBankApiPending) {
            $this->kitchenDispatchService->dispatchNewItems(
                $order,
                $payload['routing']['kitchen_print_mode'] ?? 'station',
                (bool) ($payload['routing']['kitchen_separate_multiples'] ?? false)
            );
        }

        try {
            broadcast(new OrderUpdated($order->fresh(['items', 'payments'])));
        } catch (\Throwable $e) {
            Log::error("🚨 [WebSockets] Falha ao transmitir broadcast do pedido append #{$order->id}: " . $e->getMessage());
        }

        return $order;
    }

    protected function recalculatePaymentIntents(Order $order, array $routing): void
    {
        // 1. Atualiza Status
        if (isset($routing['payment_intent_status'])) {
            $order->payment_intent_status = $routing['payment_intent_status'];
        }

        // 2. 🚀 RESPEITO ABSOLUTO AO FRONTEND!
        // Se o frontend (Modal) mandou os métodos recalculados, nós aceitamos eles cegamente
        if (!empty($routing['payment_intent_methods'])) {
            $order->payment_intent_methods = is_array($routing['payment_intent_methods'])
                ? $routing['payment_intent_methods']
                : json_decode($routing['payment_intent_methods'], true);
        }
        // 3. Fallback Seguro: Se o modal não abriu ou mandou vazio, tenta esticar a última regra guardada na comanda
        elseif (!empty($order->payment_intent_methods) && is_array($order->payment_intent_methods) && in_array($order->type, ['delivery', 'counter'])) {
            $methods = $order->payment_intent_methods;
            if (count($methods) > 0) {
                // Modifica apenas a última forma registrada para cobrir o buraco de saldo
                $lastIndex = count($methods) - 1;
                $methods[$lastIndex]['amount'] = round((float) $methods[$lastIndex]['amount'] + (float) $order->balance_due, 2);

                // Limpa o target do troco para segurança se ele não abrange mais
                if (isset($methods[$lastIndex]['change_target']) && (float) $methods[$lastIndex]['change_target'] < $methods[$lastIndex]['amount']) {
                    $methods[$lastIndex]['change_target'] = null;
                }

                $order->payment_intent_methods = $methods;
            }
        }

        // 4. Analisa a sobra de troco declarada
        $changeTarget = $routing['cash_change_target']
            ?? ($routing['change_target']
            ?? ($routing['delivery_change_target']
            ?? $order->change_target));

        if (!empty($changeTarget)) {
            $targetFloat = (float) $changeTarget;
            $order->change_target = $targetFloat;

            if ($targetFloat < (float) $order->total) {
                Log::warning("⚠️ [OrderAppend] Pedido #{$order->id}: Novo total (R$ {$order->total}) excede o valor para troco informado (R$ {$targetFloat}).");
                $order->change_target = null;
            }
        }
    }
}
