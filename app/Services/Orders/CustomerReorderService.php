<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Product;
use App\Services\Financial\PaymentGatewayManager;
use App\Services\Catalog\InventoryService;
use Illuminate\Support\Facades\DB;
use Exception;

class CustomerReorderService
{
    public function __construct(
        protected PaymentGatewayManager $paymentGateway,
        protected InventoryService $inventoryService
    ) {}

    public function processReorder(Order $oldOrder, string $newPaymentAction): array
    {
        // 🚀 BLINDAGEM: Só cancela se o pedido for um rascunho/PIX pendente que expirou.
        // Pedidos já concluídos (delivered/completed) JAMAIS devem ser cancelados retroativamente!
        if ($oldOrder->status === 'pending_payment') {
            $oldOrder->update([
                'status' => 'cancelled',
                'cancel_reason' => 'PIX Expirado / Refeito pelo cliente'
            ]);
        }

        $unavailableItems = $this->checkAvailability($oldOrder);
        if (count($unavailableItems) > 0) {
            $nomes = implode(', ', $unavailableItems);
            throw new Exception("Os seguintes itens não estão mais disponíveis: {$nomes}.", 422);
        }

        return DB::transaction(function () use ($oldOrder, $newPaymentAction) {
            $newOrder = $oldOrder->replicate();
            $newOrder->status = 'pending_payment';
            $newOrder->payment_status = 'pending';
            $newOrder->created_at = now();
            $newOrder->updated_at = now();

            $meta = $newOrder->meta ?? [];
            unset($meta['pix_qr_code'], $meta['pix_copia_e_cola'], $meta['transaction_id']);
            $newOrder->meta = $meta;

            $newOrder->save();

            $oldOrder->load('items.modifiers', 'items.subItems.modifiers');

            foreach ($oldOrder->items as $item) {
                if ($item->parent_item_id) continue;

                $newItem = $item->replicate();
                $newItem->order_id = $newOrder->id;
                $newItem->save();

                foreach ($item->modifiers as $mod) {
                    $newMod = $mod->replicate();
                    $newMod->order_item_id = $newItem->id;
                    $newMod->save();
                }

                foreach ($item->subItems as $subItem) {
                    $newSubItem = $subItem->replicate();
                    $newSubItem->order_id = $newOrder->id;
                    $newSubItem->parent_item_id = $newItem->id;
                    $newSubItem->save();

                    foreach ($subItem->modifiers as $subMod) {
                        $newSubMod = $subMod->replicate();
                        $newSubMod->order_item_id = $newSubItem->id;
                        $newSubMod->save();
                    }
                }

                $newItem->load(['modifiers', 'subItems.modifiers']);
                $this->inventoryService->processOrderItemStock($newItem, 'deduct');
            }

            $pixData = null;
            if ($newPaymentAction === 'same' && str_contains(strtolower($oldOrder->payment_method_name), 'pix')) {
                $gatewayResponse = $this->paymentGateway->processPayment($newOrder);

                if (!$gatewayResponse['success']) {
                    throw new Exception("Falha ao comunicar com o banco para gerar o novo PIX.");
                }

                $pixData = $gatewayResponse['pix_data'] ?? null;
            }

            return [
                'new_order' => $newOrder,
                'pix_data'  => $pixData
            ];
        });
    }

    private function checkAvailability(Order $order): array
    {
        $unavailable = [];
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            if (!$product || !$product->is_active) {
                $unavailable[] = $item->product_name;
            } elseif ($product->track_inventory && $product->current_stock < $item->quantity) {
                $unavailable[] = $item->product_name . ' (Estoque insuficiente)';
            }
        }
        return array_unique($unavailable);
    }
}
