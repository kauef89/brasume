<?php

namespace App\Filament\Resources\GoodsReceipts\Pages;

use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\AccountPayable;
use App\Models\CashShift;
use App\Models\GoodsReceipt;
use App\Models\ProductCostHistory;
use App\Models\ReceivedInvoice;
use App\Models\Transaction;
use App\Services\Catalog\InventoryService;
use App\Services\Catalog\ProductCostingService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateGoodsReceipt extends CreateRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['is_retroactive']) || ($data['payment_origin'] ?? null) === 'retroactive') {
            $data['payment_origin'] = 'retroactive';
            $data['requires_nfe'] = false;
            $data['status'] = 'reconciled';
        } elseif (empty($data['requires_nfe'])) {
            $data['status'] = 'reconciled';
        }

        unset($data['is_retroactive']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $record->load(['items.product', 'supplier']);

        $isRetroactive = $record->payment_origin === 'retroactive';
        $purchaseDate = Carbon::parse($record->received_at ?? now())->setTimeFrom(now());

        $inventoryService = app(InventoryService::class);
        $totalAmount = 0.0;

        $supplierName = $record->supplier?->trade_name ?: ($record->supplier?->name ?? 'Fornecedor Avulso');
        $dateStr = $purchaseDate->format('Ymd');
        $timeStr = $purchaseDate->format('His');

        $fakeNfeKey = $isRetroactive ? null : "ROMANEIO-{$record->id}-{$dateStr}-{$timeStr}";

        if (!$isRetroactive) {
            ReceivedInvoice::create([
                'nfe_key'      => $fakeNfeKey,
                'emitter_name' => $supplierName,
                'emitter_cnpj' => $record->supplier?->document ?? '00000000000000',
                'total_value'  => 0,
                'issue_date'   => $purchaseDate,
                'status'       => 'processed',
            ]);
        }

        $receiptLabel = $record->receipt_number ?: "#{$record->id}";

        foreach ($record->items as $item) {
            $product = $item->product;

            if (!$product) {
                continue;
            }

            if (!$isRetroactive && $product->track_inventory) {
                $inventoryService->addStock($product, (float) $item->quantity, 'goods_receipt');
            }

            $factor = (float) ($item->conversion_factor ?: 1);
            if ($factor <= 0) {
                $factor = 1.0;
            }

            $rawQty           = (float) ($item->raw_quantity ?: $item->quantity ?: 1);
            $cost             = (float) ($item->unit_cost_estimated ?? $product->base_cost ?? 0);
            $originalUnitCost = round($cost * $factor, 5);
            $totalAmount     += round($originalUnitCost * $rawQty, 2);

            if ($isRetroactive) {
                $item->update(['status' => 'reconciled']);
            }

            $cleanPurchaseUom = str_replace('_LATA', '', (string) ($item->purchase_uom ?: $product->unit_of_measure));

            $history = new ProductCostHistory([
                'product_id'               => $product->id,
                'supplier_id'              => $record->supplier_id,
                'nfe_key'                  => $fakeNfeKey,
                'xml_name'                 => $product->name . " (Canhoto {$receiptLabel})",
                'quantity'                 => $item->quantity,
                'conversion_factor'        => $factor,
                'unit_cost'                => $cost,
                'original_quantity'        => $rawQty,
                'original_unit_of_measure' => $cleanPurchaseUom,
                'original_unit_cost'       => $originalUnitCost,
                'source_type'              => ProductCostHistory::SOURCE_GOODS_RECEIPT,
                'origin_type'              => GoodsReceipt::class,
                'origin_id'                => $record->id,
            ]);
            $history->created_at = $purchaseDate;
            $history->updated_at = $purchaseDate;
            $history->save();

            // Se o cadastro mestre ainda não tinha fator configurado (<= 1), memoriza o fator informado
            if ((float) ($product->conversion_factor ?? 1) <= 1 && $factor !== 1.0) {
                $product->updateQuietly(['conversion_factor' => $factor]);
            }

            $hasNewerPurchase = ProductCostHistory::where('product_id', $product->id)
                ->where('id', '!=', $history->id)
                ->where('created_at', '>', $purchaseDate)
                ->exists();

            if (!$hasNewerPurchase && $cost > 0 && (float) $product->base_cost !== $cost) {
                $product->update(['base_cost' => $cost]);
            }
        }

        ProductCostingService::clearCache();

        $recordUpdate = ['total_amount' => $totalAmount];
        if ($isRetroactive || !$record->requires_nfe) {
            $recordUpdate['status'] = 'reconciled';
        }
        $record->update($recordUpdate);

        if ($isRetroactive) {
            return;
        }

        ReceivedInvoice::where('nfe_key', $fakeNfeKey)->update(['total_value' => $totalAmount]);

        $origin = $record->payment_origin ?? 'payable';

        if ($origin === 'cash_drawer' && $totalAmount > 0) {
            $shift = CashShift::where('status', 'open')->latest()->first();
            if ($shift) {
                Transaction::create([
                    'cash_shift_id'    => $shift->id,
                    'bank_account_id'  => $shift->bank_account_id,
                    'type'             => 'out',
                    'category'         => 'cash_out',
                    'amount'           => $totalAmount,
                    'description'      => "Sangria: Compra de Insumos - Canhoto {$receiptLabel} ({$supplierName})",
                    'operator_name'    => auth()->user()?->name ?? 'Sistema',
                    'transaction_date' => now(),
                ]);
            }
        } elseif ($origin === 'bank_account' && $totalAmount > 0 && $record->bank_account_id) {
            Transaction::create([
                'bank_account_id'  => $record->bank_account_id,
                'type'             => 'out',
                'category'         => 'supplier_payment',
                'amount'           => $totalAmount,
                'description'      => "Pagamento de Compras (Canhoto {$receiptLabel}) - {$supplierName}",
                'operator_name'    => auth()->user()?->name ?? 'Sistema',
                'transaction_date' => now(),
            ]);
        } elseif ($origin === 'partner' && $totalAmount > 0) {
            AccountPayable::create([
                'supplier_id'   => $record->supplier_id,
                'supplier_name' => "Reembolso Sócio - {$supplierName}",
                'description'   => "Compra Avulsa Canhoto {$receiptLabel}",
                'amount'        => $totalAmount,
                'installment'   => '1/1',
                'due_date'      => $record->due_date ?: now()->addDays(7)->toDateString(),
                'status'        => 'pending',
            ]);
        } elseif ($origin === 'payable' && $totalAmount > 0) {
            AccountPayable::create([
                'supplier_id'   => $record->supplier_id,
                'supplier_name' => $supplierName,
                'description'   => "Compra a Prazo Direto (Canhoto {$receiptLabel})",
                'amount'        => $totalAmount,
                'installment'   => '1/1',
                'due_date'      => $record->due_date ?: now()->toDateString(),
                'status'        => 'pending',
            ]);
        }
    }
}
