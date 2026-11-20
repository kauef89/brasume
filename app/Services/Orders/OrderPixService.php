<?php

namespace App\Services\Orders;

use App\Enums\PaymentEngineType;
use App\Events\OrderUpdated;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Financial\Gateways\SicrediPixGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderPixService
{
    /**
     * Orquestra a geração de cobrança PIX via Gateway e atualiza o pedido.
     */
    public function generateCharge(Order $order, ?float $requestedAmount, SicrediPixGateway $pixGateway): array
    {
        $settings = CompanySetting::getCached() ?? CompanySetting::first();

        if (!$settings || !$settings->sicredi_is_active) {
            throw new Exception("A integração PIX Sicredi não está ativa nas configurações.");
        }

        $alreadyPaid = (float) $order->payments()->where('is_paid', true)->sum('amount');
        $totalOutstanding = max(0, round((float) $order->total - $alreadyPaid, 2));

        // Cancela eventual cobrança PIX anterior ainda em aberto antes de gerar outra
        if ($order->payments()->where('status', 'pending')->where('is_paid', false)->exists()) {
            $this->cancelIntent($order, false);
        }

        if ($totalOutstanding <= 0.01) {
            throw new Exception("Este pedido já está quitado.");
        }

        $targetAmount = $requestedAmount ? (float) $requestedAmount : $totalOutstanding;
        $balanceDue = min($targetAmount, $totalOutstanding);

        if ($balanceDue < 0.01) {
            throw new Exception("O valor solicitado para a cobrança PIX é inválido.");
        }

        $pixData = $pixGateway->generatePayment($order, $balanceDue);
        $expiresAt = now()->addSeconds(3600);

        $paymentMethod = PaymentMethod::whereIn('engine_type', [PaymentEngineType::BANK_API, 'bank_api', 'sicredi_pix'])->first()
            ?? PaymentMethod::where('name', 'like', '%Pix%')->first();

        $shiftId = $order->cash_shift_id
            ?? \App\Models\CashShift::where('status', 'open')->latest('id')->value('id');

        $order->payments()->create([
            'payment_method_id' => $paymentMethod?->id,
            'cash_shift_id'     => $shiftId,
            'method'            => $paymentMethod?->name ?? 'Pix',
            'amount'            => $balanceDue,
            'status'            => 'pending',
            'is_paid'           => false,
            'txid'              => $pixData['txid'],
            'pix_payload'       => $pixData['qr_code'],
        ]);

        $order->update([
            'txid'                  => $pixData['txid'],
            'pix_payload'           => $pixData['qr_code'],
            'pix_expires_at'        => $expiresAt,
            'payment_intent_status' => 'pending_pix',
        ]);

        broadcast(new OrderUpdated($order->fresh(['items', 'payments'])));

        return [
            'txid'       => $pixData['txid'],
            'qr_code'    => $pixData['qr_code'],
            'amount'     => $balanceDue,
            'order_id'   => $order->id,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Cancela a intenção PIX pendente do pedido, invalida o registro pendente em payments
     * e libera o pedido para a fila operacional caso estivesse retido em pending_payment.
     */
    public function cancelIntent(Order $order, bool $releaseToKitchen = true): void
    {
        $txidsToCancel = collect([$order->txid])
            ->merge(
                $order->payments()
                    ->where('is_paid', false)
                    ->where('status', 'pending')
                    ->pluck('txid')
            )
            ->filter()
            ->unique()
            ->values();

        if ($txidsToCancel->isNotEmpty()) {
            try {
                $gateway = app(SicrediPixGateway::class);
                foreach ($txidsToCancel as $txid) {
                    $gateway->cancelCharge((string) $txid);
                }
            } catch (\Throwable $e) {
                Log::warning("⚠️ [OrderPixService] Erro ao notificar Sicredi no cancelamento do PIX do Pedido #{$order->id}: " . $e->getMessage());
            }
        }

        $wasWaitingPayment = ($order->status === 'pending_payment');

        DB::transaction(function () use ($order, $releaseToKitchen, $wasWaitingPayment) {
            $order->payments()
                ->where('is_paid', false)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            $filteredIntents = null;
            if (is_array($order->payment_intent_methods)) {
                $remaining = array_values(array_filter($order->payment_intent_methods, function ($m) {
                    $eng = (string) ($m['engine_type'] ?? '');
                    $name = mb_strtolower((string) ($m['method_name'] ?? ''));
                    return $eng !== 'bank_api' && !str_contains($name, 'pix');
                }));
                $filteredIntents = !empty($remaining) ? $remaining : null;
            }

            $updatePayload = [
                'txid'                   => null,
                'pix_payload'            => null,
                'pix_expires_at'         => null,
                'payment_intent_methods' => $filteredIntents,
            ];

            if (in_array($order->payment_intent_status, ['pix_waiting', 'pending_pix', null], true)) {
                $updatePayload['payment_intent_status'] = 'pending_choice';
            }

            if ($releaseToKitchen && $wasWaitingPayment) {
                $updatePayload['status'] = 'pending';
            }

            $order->update($updatePayload);

            if ($releaseToKitchen && $wasWaitingPayment) {
                OrderItem::where('order_id', $order->id)
                    ->where('status', '!=', 'cancelled')
                    ->update([
                        'status'            => 'active',
                        'production_status' => 'pending',
                    ]);
            }
        });

        $freshOrder = $order->fresh(['items.modifiers', 'items.product', 'payments.paymentMethod']);

        if ($releaseToKitchen && $wasWaitingPayment) {
            try {
                $inventoryService = app(\App\Services\Catalog\InventoryService::class);
                foreach ($freshOrder->items as $item) {
                    if ($item->parent_item_id === null && $item->status !== 'cancelled') {
                        $inventoryService->processOrderItemStock($item, 'deduct');
                    }
                }
            } catch (\Throwable $e) {
                Log::error("🚨 [PIX CANCEL INVENTORY] Pedido #{$freshOrder->id}: " . $e->getMessage());
            }

            try {
                app(\App\Services\Orders\CustomerOrderSideEffects::class)->handle(
                    order: $freshOrder,
                    isAppend: false,
                    needsPixPayment: false
                );
            } catch (\Throwable $e) {
                Log::error("🚨 [PIX CANCEL SIDE-EFFECTS] Pedido #{$freshOrder->id}: " . $e->getMessage());
            }
        }

        broadcast(new OrderUpdated($freshOrder));
    }

    /**
     * Processa a conciliação do Webhook bancário e dispara os efeitos colaterais.
     */
    public function handleWebhook(string $txid, float $amount, ?string $endToEndId = null): Order
    {
        $payment = Payment::where('txid', $txid)->first();
        $order = null;

        if ($payment) {
            $order = $payment->order;
            $resolvedShiftId = $payment->cash_shift_id
                ?? $order?->cash_shift_id
                ?? \App\Models\CashShift::where('status', 'open')->latest('id')->value('id');

            $payment->update([
                'cash_shift_id' => $resolvedShiftId,
                'status'        => 'completed',
                'is_paid'       => true,
                'end_to_end_id' => $endToEndId,
                'amount'        => $amount
            ]);
        } else {
            $order = Order::where('txid', $txid)->first();
            if ($order) {
                $payment = $order->payments()->first();
                if ($payment) {
                    $payment->update([
                        'status'        => 'completed',
                        'is_paid'       => true,
                        'end_to_end_id' => $endToEndId,
                        'amount'        => $amount
                    ]);
                }
            }
        }

        if (!$order) {
            throw new Exception("Pedido ou Pagamento não encontrado para TXID: {$txid}");
        }

        $updateData = [
            'payment_status'        => 'paid',
            'payment_intent_status' => 'paid',
        ];
        if ($order->notes === 'Cancelado pelo cliente via App') {
            $updateData['notes'] = null;
        }

        if (in_array($order->type, [\App\Enums\OrderType::TABLE, \App\Enums\OrderType::COUNTER], true)) {
            $updateData['status'] = 'closed';
            $updateData['closed_at'] = now();
        } elseif (in_array($order->status, ['pending_payment', 'pending', 'cancelled'])) {
            $updateData['status'] = 'pending';
        }

        $order->update($updateData);

        OrderItem::where('order_id', $order->id)->update([
            'status'            => 'active',
            'production_status' => 'pending',
            'cancel_reason'     => null,
            'paid_qty'          => DB::raw('quantity'),
        ]);

        $order->load(['items.modifiers', 'items.product', 'payments']);

        // Baixa de estoque
        try {
            $inventoryService = app(\App\Services\Catalog\InventoryService::class);
            foreach ($order->items as $item) {
                if ($item->parent_item_id !== null) {
                    continue;
                }
                $inventoryService->processOrderItemStock($item, 'deduct');
            }
        } catch (\Throwable $e) {
            Log::error("🚨 [PIX WEBHOOK INVENTORY] Pedido #{$order->id}: " . $e->getMessage());
        }

        // KDS e Notificações
        try {
            app(\App\Services\Orders\CustomerOrderSideEffects::class)->handle(
                order: $order,
                isAppend: false,
                needsPixPayment: false
            );
        } catch (\Throwable $e) {
            Log::error("🚨 [PIX WEBHOOK SIDE-EFFECTS] Pedido #{$order->id}: " . $e->getMessage());
        }

        // Impressão
        try {
            if (class_exists(\App\Services\Printing\OrderPrintService::class)) {
                app(\App\Services\Printing\OrderPrintService::class)->dispatchCheckoutReceipts($order, $payment);
            }
        } catch (\Throwable $e) {
            Log::error("🚨 [PIX WEBHOOK PRINT] Pedido #{$order->id}: " . $e->getMessage());
        }

        try {
            broadcast(new OrderUpdated($order->fresh(['items', 'payments']), true));
        } catch (\Throwable $e) {
            Log::error("🚨 [PIX WEBHOOK BROADCAST] Pedido #{$order->id}: " . $e->getMessage());
        }

        return $order;
    }
}
