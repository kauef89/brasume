<?php

namespace App\Observers;

use App\Models\GoodsReceipt;
use App\Models\Transaction;
use App\Models\AccountPayable;
use App\Models\ReceivedInvoice;
use App\Models\ProductCostHistory;
use App\Services\Catalog\InventoryService;
use Illuminate\Support\Facades\DB;

class GoodsReceiptObserver
{
    public function deleting(GoodsReceipt $goodsReceipt): void
    {
        // 🚀 OTIMIZAÇÃO: Tudo numa única transação
        DB::transaction(function () use ($goodsReceipt) {
            $inventoryService = app(InventoryService::class);
            $goodsReceipt->load('items.product');

            // 1. REVERTER O ESTOQUE NO KARDEX
            foreach ($goodsReceipt->items as $item) {
                $product = $item->product;

                if ($product && $product->track_inventory) {
                    $inventoryService->deductStock(
                        product: $product,
                        qtySold: (float) $item->quantity,
                        originModel: $goodsReceipt,
                        description: "Estorno de Entrada: Romaneio #{$goodsReceipt->receipt_number} excluído"
                    );
                }
            }

            // 2. REVERTER O FINANCEIRO
            if ($goodsReceipt->receipt_number) {
                Transaction::where('description', 'LIKE', "%Canhoto #{$goodsReceipt->receipt_number}%")
                    ->where('amount', $goodsReceipt->total_amount)
                    ->delete();

                AccountPayable::where('description', 'LIKE', "%Canhoto #{$goodsReceipt->receipt_number}%")
                    ->where('amount', $goodsReceipt->total_amount)
                    ->delete();
            }

            // 3. REVERTER HISTÓRICO DE CUSTOS E NF-E FANTASMA
            ProductCostHistory::where(function ($query) use ($goodsReceipt) {
                $query->where('origin_type', GoodsReceipt::class)
                      ->where('origin_id', $goodsReceipt->id);
            })->orWhere('nfe_key', 'LIKE', "ROMANEIO-{$goodsReceipt->id}-%")->delete();

            $fakeNfeKeyPrefix = "ROMANEIO-{$goodsReceipt->id}-";
            ReceivedInvoice::where('nfe_key', 'LIKE', "{$fakeNfeKeyPrefix}%")->delete();
        });
    }
}
