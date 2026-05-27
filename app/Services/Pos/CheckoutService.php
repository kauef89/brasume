<?php

namespace App\Services\Pos;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Transaction;
use App\Models\CompanySetting;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Exception;

class CheckoutService
{
    /**
     * Retorna os dados necessários para renderizar a tela de Checkout
     */
    public function getCheckoutData(Order $order): array
    {
        return [
            'order' => $order->load(['items.modifiers', 'payments']),
            'paymentMethods' => PaymentMethod::where('is_active', true)->get(),
            'companySettings' => CompanySetting::first(),
        ];
    }

    /**
     * Adiciona rapidamente mais uma unidade de um produto existente no pedido
     */
    public function quickAdd(Order $order, int $itemId): void
    {
        DB::transaction(function () use ($order, $itemId) {
            $item = OrderItem::where('order_id', $order->id)->findOrFail($itemId);

            $item->increment('quantity');
            $item->update(['total_price' => $item->quantity * $item->unit_price]);

            // Recalcula os totais do pedido pai
            $order->increment('subtotal', $item->unit_price);
            $order->increment('total', $item->unit_price);
        });
    }

    /**
     * Cancela/Estorna um item parcial do carrinho com validação de PIN de gerente
     */
    public function cancelItem(Order $order, array $data): void
    {
        // 1. Validação de segurança
        $user = User::where('pin', $data['pin'])->first();
        if (!$user || !$user->hasAnyRole(['admin', 'gerente', 'manager'])) {
            throw new Exception('PIN de segurança inválido ou sem nível administrativo.');
        }

        DB::transaction(function () use ($order, $data, $user) {
            $item = OrderItem::where('order_id', $order->id)->findOrFail($data['item_id']);

            // Se houver perda operacional, lança uma saída no caixa
            if (!empty($data['is_loss'])) {
                Transaction::create([
                    'type' => 'out',
                    'amount' => $item->unit_price * $item->quantity,
                    'description' => "Perda Operacional (Item: {$item->product_name} cancelado por: {$data['reason']}) - Ref Pedido #{$order->id}",
                    'operator_name' => $user->name,
                    'order_id' => $order->id
                ]);
            }

            // Subtrai os valores do pedido principal
            $reduction = $item->total_price;
            $order->decrement('subtotal', $reduction);
            $order->decrement('total', $reduction);

            // Deleta o item do pedido
            $item->delete();
        });
    }

    /**
     * Processa pagamentos (parciais ou totais) e dá baixa no status do pedido
     */
    public function processPayment(Order $order, array $data): void
    {
        DB::transaction(function () use ($order, $data) {
            // Lança os pagamentos recebidos no banco
            foreach ($data['payments'] as $payment) {
                $order->payments()->create([
                    'payment_method_id' => $payment['method_id'],
                    'amount' => $payment['amount'],
                    'method' => PaymentMethod::find($payment['method_id'])->name ?? 'Outros'
                ]);
            }

            // Atualiza as quantidades pagas nos itens enviados pelo front-end
            foreach ($data['items'] as $pItem) {
                OrderItem::where('id', $pItem['id'])->update([
                    'paid_qty' => DB::raw("paid_qty + " . $pItem['pay_qty'])
                ]);
            }

            // Se habilitada taxa de serviço, atualiza no pedido
            if (isset($data['service_fee'])) {
                $order->update(['service_fee' => $data['service_fee']]);
            }

            // Se o total pago bater ou passar o total do pedido, finaliza a conta
            $totalPaid = $order->payments()->sum('amount');
            if ($totalPaid >= $order->total) {
                $order->update([
                    'status' => 'completed',
                    'payment_status' => 'paid',
                ]);
            } else {
                $order->update(['payment_status' => 'partial']);
            }
        });
    }
}
