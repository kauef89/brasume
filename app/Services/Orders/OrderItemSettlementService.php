<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Collection;

class OrderItemSettlementService
{
    /**
     * Executa a quitação granular dos itens selecionados no pagamento.
     */
    public function settleItems(Order $order, array $itemsData, ?Payment $targetPayment = null, ?float $fallbackPaymentAmount = null): void
    {
        if (empty($itemsData)) {
            return;
        }

        foreach ($itemsData as $pItem) {
            if (!isset($pItem['id']) || !isset($pItem['pay_qty'])) continue;

            $payQty = (float) $pItem['pay_qty'];
            if ($payQty <= 0) continue;

            $item = OrderItem::where('order_id', $order->id)->find($pItem['id']);
            if (!$item) continue;

            $available = max(0, (float) $item->quantity - (float) $item->paid_qty);
            $toAdd = min($payQty, $available);

            if ($toAdd > 0) {
                $item->paid_qty = round((float) $item->paid_qty + $toAdd, 4);
                $item->save();

                $itemCoveredAmount = isset($pItem['amount']) && (float) $pItem['amount'] > 0
                    ? (float) $pItem['amount']
                    : round($toAdd * (float) $item->unit_price, 2);

                if ($itemCoveredAmount <= 0 && count($itemsData) === 1 && $fallbackPaymentAmount > 0) {
                    $itemCoveredAmount = $fallbackPaymentAmount;
                }

                if ($targetPayment) {
                    $targetPayment->items()->attach($item->id, [
                        'quantity' => $toAdd,
                        'amount'   => $itemCoveredAmount
                    ]);
                }

                // Rateio proporcional no item pai (Combo)
                if ($item->parent_item_id) {
                    $parent = OrderItem::find($item->parent_item_id);
                    if ($parent && (float) $parent->total_price > 0) {
                        $proportionalAdvance = ($itemCoveredAmount / (float) $parent->total_price) * (float) $parent->quantity;
                        $parent->paid_qty = min((float) $parent->quantity, round((float) $parent->paid_qty + $proportionalAdvance, 4));
                        $parent->save();
                    }
                }

                // Baixa proporcional nos subitens
                $subItems = OrderItem::where('parent_item_id', $item->id)->get();
                if ($subItems->isNotEmpty() && (float) $item->quantity > 0) {
                    $ratio = $toAdd / (float) $item->quantity;
                    foreach ($subItems as $sub) {
                        $subToAdd = (float) $sub->quantity * $ratio;
                        $sub->paid_qty = min((float) $sub->quantity, round((float) $sub->paid_qty + $subToAdd, 4));
                        $sub->save();
                    }
                }
            }
        }
    }

    /**
     * Verifica se todos os itens ativos da comanda foram liquidados.
     */
    public function areAllItemsPaid(Order $order): bool
    {
        $unpaidCount = $order->items()
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereNull('paid_qty')
                  ->orWhereRaw('paid_qty < quantity - 0.001');
            })
            ->count();

        return $unpaidCount === 0;
    }
}
