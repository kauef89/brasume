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

        $alreadyPaid = (float) $order->payments()->where('status', 'paid')->sum('amount');
        $totalOutstanding = max(0, round((float) $order->total - $alreadyPaid, 2));

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

        $order->payments()->create([
            'payment_method_id' => $paymentMethod?->id,
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
     * Cancela a intenção PIX pendente do pedido.
     */
    public function cancelIntent(Order $order): void
    {
        $order->update([
            'txid'                  => null,
            'pix_payload'           => null,
            'pix_expires_at'        => null,
            'payment_intent_status' => null,
        ]);

        broadcast(new OrderUpdated($order->fresh(['items', 'payments'])));
    }

    /**
     * Processa a conciliação do Webhook bancário e dispara os efeitos colaterais.
     */
    public function handleWebhook(string $txid, float $amount, ?string $endToEndId = null): Order
    {
        $payment = Payment::where('txid', $txid)->first();
        $order = null;

        if ($payment) {
            $payment->update([
                'status'        => 'completed',
                'is_paid'       => true,
                'end_to_end_id' => $endToEndId,
                'amount'        => $amount
            ]);
            $order = $payment->order;
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

        if (in_array($order->status, ['pending_payment', 'pending', 'cancelled'])) {
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

        return $order;
    }
}
