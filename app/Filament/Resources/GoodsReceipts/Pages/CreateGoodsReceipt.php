<?php

namespace App\Filament\Resources\GoodsReceipts\Pages;

use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\Product;
use App\Models\CashShift;
use App\Models\Transaction;
use App\Models\AccountPayable;
use App\Models\ReceivedInvoice;
use App\Services\Catalog\InventoryService;
use Filament\Resources\Pages\CreateRecord;
use Carbon\Carbon;

class CreateGoodsReceipt extends CreateRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        // 🚀 Forçamos o recarregamento profundo de todos os itens e do produto recém-criado pelo Quick Create
        $record->load(['items.product', 'supplier']);

        $inventoryService = app(InventoryService::class);
        $totalAmount = 0.0;

        $supplierName = $record->supplier?->trade_name ?: ($record->supplier?->name ?? 'Fornecedor Avulso');
        $dateStr = Carbon::parse($record->received_at ?? now())->format("Ymd");
        $timeStr = now()->format("His");

        // Geramos uma chave de NF-e falsa para linkar o romaneio ao histórico
        $fakeNfeKey = "ROMANEIO-{$record->id}-{$dateStr}-{$timeStr}";

        // Gravamos uma invoice avulsa (espelho) para não quebrar a tela de histórico
        ReceivedInvoice::create([
            "nfe_key"      => $fakeNfeKey,
            "emitter_name" => $supplierName,
            "emitter_cnpj" => $record->supplier?->document ?? "00000000000000",
            "total_value"  => 0, // Atualizaremos no final do loop
            "issue_date"   => Carbon::parse($record->received_at ?? now()),
            "status"       => "processed",
        ]);

        foreach ($record->items as $item) {
            // Pegamos o produto atualizado direto da relação carregada
            $product = $item->product;

            if (! $product) {
                continue;
            }

            if ($product->track_inventory) {
                $inventoryService->addStock($product, (float) $item->quantity, 'goods_receipt');
            }

            $cost = (float) ($item->unit_cost_estimated ?? $product->base_cost ?? 0);
            $totalAmount += round($cost * (float) $item->quantity, 2);

            // 🚀 1. ATUALIZA O CUSTO BASE DO INSUMO COM O NOVO VALOR PAGO
            if ($cost > 0 && $cost != $product->base_cost) {
                $product->update(['base_cost' => $cost]);
            }

            // 🚀 2. REGISTRA O HISTÓRICO DE COMPRA
            $product->costHistories()->create([
                "supplier_id"              => $record->supplier_id,
                "nfe_key"                  => $fakeNfeKey,
                "xml_name"                 => $product->name . " (Canhoto #{$record->receipt_number})",
                "quantity"                 => $item->quantity,
                "conversion_factor"        => $item->conversion_factor ?? 1,
                "unit_cost"                => $cost,
                "original_quantity"        => $item->raw_quantity ?? $item->quantity,
                "original_unit_of_measure" => $item->purchase_uom ?? $product->unit_of_measure,
                "original_unit_cost"       => $cost,
            ]);
        }

        $record->update(['total_amount' => $totalAmount]);
        ReceivedInvoice::where('nfe_key', $fakeNfeKey)->update(['total_value' => $totalAmount]);

        // 🚀 Fechamento Financeiro de acordo com a origem selecionada
        $origin = $record->payment_origin ?? 'payable';

        if ($origin === 'cash_drawer' && $totalAmount > 0) {
            $shift = CashShift::where('status', 'open')->latest()->first();
            if ($shift) {
                Transaction::create([
                    'cash_shift_id'   => $shift->id,
                    'bank_account_id' => $shift->bank_account_id,
                    'type'            => 'out',
                    'category'        => 'cash_out',
                    'amount'          => $totalAmount,
                    'description'     => "Sangria: Compra de Insumos - Canhoto #{$record->receipt_number} ({$supplierName})",
                    'operator_name'   => auth()->user()?->name ?? 'Sistema',
                    'transaction_date'=> now(),
                ]);
            }
        }
        // 🚀 PAGAMENTO VIA BANCO (PIX, Cartão, Transferência)
        elseif ($origin === 'bank_account' && $totalAmount > 0 && $record->bank_account_id) {
            Transaction::create([
                'bank_account_id' => $record->bank_account_id,
                'type'            => 'out',
                'category'        => 'supplier_payment',
                'amount'          => $totalAmount,
                'description'     => "Pagamento de Compras (Canhoto #{$record->receipt_number}) - {$supplierName}",
                'operator_name'   => auth()->user()?->name ?? 'Sistema',
                'transaction_date'=> now(),
            ]);
        }
        elseif ($origin === 'partner' && $totalAmount > 0) {
            AccountPayable::create([
                'supplier_id'   => $record->supplier_id,
                'supplier_name' => "Reembolso Sócio - {$supplierName}",
                'description'   => "Compra Avulsa Canhoto #{$record->receipt_number}",
                'amount'        => $totalAmount,
                'installment'   => '1/1',
                'due_date'      => $record->due_date ?: now()->addDays(7)->toDateString(),
                'status'        => 'pending',
            ]);
        }
        elseif ($origin === 'payable' && $totalAmount > 0) {
            AccountPayable::create([
                'supplier_id'   => $record->supplier_id,
                'supplier_name' => $supplierName,
                'description'   => "Compra a Prazo Direto (Canhoto #{$record->receipt_number})",
                'amount'        => $totalAmount,
                'installment'   => '1/1',
                'due_date'      => $record->due_date ?: now()->toDateString(),
                'status'        => 'pending',
            ]);
        }
    }
}
