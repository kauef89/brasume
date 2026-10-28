<?php

namespace App\Observers;

use App\Enums\AccountingCategory;

use App\Models\GoodsReceipt;
use App\Models\AccountPayable;

class GoodsReceiptObserver
{
    /**
     * Quando um romaneio é recebido, se for de um fornecedor informal (sem nota prevista),
     * gera imediatamente a provisão a pagar para liquidação contra o extrato bancário.
     */
    public function updated(GoodsReceipt $receipt): void
    {
        if ($receipt->isDirty('status') && $receipt->status === 'received') {
            $this->provisionFinancialPayable($receipt);
        }
    }

    public function created(GoodsReceipt $receipt): void
    {
        if ($receipt->status === 'received') {
            $this->provisionFinancialPayable($receipt);
        }
    }

    private function provisionFinancialPayable(GoodsReceipt $receipt): void
    {
        // Se já foi conciliado com uma NF-e, a própria nota já gera a cobrança
        if (!empty($receipt->nfe_key)) {
            return;
        }

        $receipt->loadMissing('items');
        $totalAmount = (float) $receipt->items->sum(function ($item) {
            return (float) ($item->unit_cost ?? 0) * (float) ($item->quantity ?? 0);
        });

        if ($totalAmount <= 0) {
            return;
        }

        $receiptNum = $receipt->receipt_number ?: "#{$receipt->id}";
        $desc = "Romaneio Canhoto {$receiptNum} - " . ($receipt->supplier?->trade_name ?: $receipt->supplier?->name ?: 'Fornecedor');

        // Evita duplicidade
        $exists = AccountPayable::where('description', 'like', "%Romaneio Canhoto {$receiptNum}%")
            ->where('supplier_id', $receipt->supplier_id)
            ->exists();

        if (!$exists) {
            AccountPayable::create([
                'supplier_id'            => $receipt->supplier_id,
                'description'            => $desc,
                'amount'                 => $totalAmount,
                'due_date'               => $receipt->received_at ?? now(),
                'status'                 => 'pending',
                'accounting_category'    => AccountingCategory::INGREDIENTS->value,
                'accounting_subcategory' => 'general',
            ]);
        }
    }
}