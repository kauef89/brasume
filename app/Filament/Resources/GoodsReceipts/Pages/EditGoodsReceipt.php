<?php

namespace App\Filament\Resources\GoodsReceipts\Pages;

use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\ReceivedInvoice;
use App\Services\Catalog\InventoryService;
use App\Services\Catalog\ProductCostingService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditGoodsReceipt extends EditRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    /** @var array<int, float> */
    protected array $originalQuantities = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['is_retroactive']) || ($data['payment_origin'] ?? null) === 'retroactive') {
            $data['payment_origin'] = 'retroactive';
            $data['requires_nfe'] = false;
            $data['status'] = 'reconciled';
        }

        unset($data['is_retroactive']);
        return $data;
    }

    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        $this->originalQuantities = [];

        foreach ($record->items as $item) {
            if ($item->product_id) {
                $pid = (int) $item->product_id;
                $this->originalQuantities[$pid] = ($this->originalQuantities[$pid] ?? 0.0) + (float) $item->quantity;
            }
        }
    }

    protected function afterSave(): void
    {
        self::syncReceiptState($this->getRecord(), $this->originalQuantities);
    }

    public static function syncReceiptState(GoodsReceipt $record, array $originalQuantities = []): void
    {
        $record->load(['items.product', 'supplier']);

        $isRetroactive = $record->payment_origin === 'retroactive';
        $purchaseDate  = Carbon::parse($record->received_at ?? now())->setTimeFrom(now());
        $receiptLabel  = $record->receipt_number ?: "#{$record->id}";

        $newQuantities = [];
        $totalAmount   = 0.0;
        $affectedPids  = array_keys($originalQuantities);

        foreach ($record->items as $item) {
            $product = $item->product;
            if (!$product) {
                continue;
            }

            $pid = (int) $product->id;
            $affectedPids[] = $pid;
            $newQuantities[$pid] = ($newQuantities[$pid] ?? 0.0) + (float) $item->quantity;

            $factor = (float) ($item->conversion_factor ?: 1);
            if ($factor <= 0) {
                $factor = 1.0;
            }

            $rawQty           = (float) ($item->raw_quantity ?: $item->quantity ?: 1);
            $cost             = (float) ($item->unit_cost_estimated ?? $product->base_cost ?? 0);
            $originalUnitCost = round($cost * $factor, 5);
            $totalAmount     += round($originalUnitCost * $rawQty, 2);

            if ($isRetroactive && $item->status !== 'reconciled') {
                $item->updateQuietly(['status' => 'reconciled']);
            }
        }

        if (!$isRetroactive && !empty($originalQuantities)) {
            $inventoryService = app(InventoryService::class);
            foreach (array_unique($affectedPids) as $pid) {
                $oldQty = $originalQuantities[$pid] ?? 0.0;
                $newQty = $newQuantities[$pid] ?? 0.0;
                $delta  = round($newQty - $oldQty, 4);

                if ($delta == 0.0) {
                    continue;
                }

                $prod = Product::find($pid);
                if ($prod && $prod->track_inventory) {
                    if ($delta > 0) {
                        $inventoryService->addStock($prod, $delta, 'goods_receipt_edit');
                    } else {
                        $inventoryService->deductStock($prod, abs($delta));
                    }
                }
            }
        }

        $hasExternalNfe = $record->items()
            ->whereNotNull('nfe_key')
            ->where('nfe_key', 'not like', 'ROMANEIO-%')
            ->exists();

        $existingHistory = ProductCostHistory::where('origin_type', GoodsReceipt::class)
            ->where('origin_id', $record->id)
            ->first();

        $fakeNfeKey = $isRetroactive
            ? null
            : ($existingHistory?->nfe_key ?: "ROMANEIO-{$record->id}-" . $purchaseDate->format('Ymd-His'));

        if (!$hasExternalNfe) {
            ProductCostHistory::where('origin_type', GoodsReceipt::class)
                ->where('origin_id', $record->id)
                ->delete();

            foreach ($record->items as $item) {
                $product = $item->product;
                if (!$product) {
                    continue;
                }

                $factor = (float) ($item->conversion_factor ?: 1);
                if ($factor <= 0) {
                    $factor = 1.0;
                }

                $rawQty           = (float) ($item->raw_quantity ?: $item->quantity ?: 1);
                $cost             = (float) ($item->unit_cost_estimated ?? $product->base_cost ?? 0);
                $originalUnitCost = round($cost * $factor, 5);
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

                if ((float) ($product->conversion_factor ?? 1) <= 1 && $factor !== 1.0) {
                    $product->updateQuietly(['conversion_factor' => $factor]);
                }
            }
        }

        foreach (array_unique($affectedPids) as $pid) {
            $prod = Product::find($pid);
            if (!$prod) {
                continue;
            }
            $latest = ProductCostHistory::where('product_id', $pid)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($latest && (float) $latest->unit_cost > 0 && (float) $prod->base_cost !== (float) $latest->unit_cost) {
                $prod->updateQuietly(['base_cost' => (float) $latest->unit_cost]);
            }
        }

        $record->updateQuietly(['total_amount' => $totalAmount]);

        if (!$isRetroactive && $fakeNfeKey) {
            ReceivedInvoice::where('nfe_key', $fakeNfeKey)->update(['total_value' => $totalAmount]);
        }

        ProductCostingService::clearCache();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function ($record, Actions\DeleteAction $action) {
                    $isRetroactive = $record->payment_origin === 'retroactive';

                    if (!$isRetroactive) {
                        $hasReconciled = $record->items()->where('status', 'reconciled')->exists();
                        if ($hasReconciled) {
                            Notification::make()
                                ->title('Ação Bloqueada')
                                ->body('Este romaneio já possui itens conciliados com uma Nota Fiscal. Não é possível excluí-lo.')
                                ->danger()
                                ->send();
                            $action->cancel();
                            return;
                        }

                        $inventoryService = app(InventoryService::class);
                        foreach ($record->items as $item) {
                            $product = Product::find($item->product_id);
                            if ($product && $product->track_inventory) {
                                $inventoryService->deductStock($product, (float) $item->quantity);
                            }
                        }
                    }

                    ProductCostHistory::where('origin_type', GoodsReceipt::class)
                        ->where('origin_id', $record->id)
                        ->delete();

                    ProductCostingService::clearCache();
                }),
        ];
    }
}
