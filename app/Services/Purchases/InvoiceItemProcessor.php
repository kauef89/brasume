<?php

namespace App\Services\Purchases;

use App\Models\GoodsReceiptItem;
use App\Models\GoodsReceipt;
use App\Models\PersonalPurchase;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\CompanyAsset;
use App\Services\Catalog\InventoryService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceItemProcessor
{
    public function resolveStorageLocationForProcurement(Product $product): ?int
    {
        if (!$product->track_inventory) {
            return null;
        }

        $locations = DB::table('product_storage_location as psl')
            ->join('storage_locations as sl', 'sl.id', '=', 'psl.storage_location_id')
            ->where('psl.product_id', $product->id)
            ->whereNull('sl.deleted_at')
            ->where('sl.is_active', 1)
            ->select('psl.*', 'sl.type as location_type')
            ->get();

        $storageLoc = $locations->firstWhere('location_type', 'storage');
        $displayLoc = $locations->firstWhere('location_type', 'display');

        // 🚀 CENÁRIO 1: Possui Armazenamento e Exposição -> Vai 100% para o Armazenamento
        if ($storageLoc && $displayLoc) {
            return (int) $storageLoc->storage_location_id;
        }

        // CENÁRIO 2: Possui apenas local de armazenamento
        if ($storageLoc) {
            return (int) $storageLoc->storage_location_id;
        }

        // CENÁRIO 3: Possui apenas exposição com local de reposição de origem (source_location_id)
        if ($displayLoc && !empty($displayLoc->source_location_id)) {
            $sourceId = (int) $displayLoc->source_location_id;
            DB::table('product_storage_locations')->updateOrInsert(
                ['product_id' => $product->id, 'storage_location_id' => $sourceId],
                ['updated_at' => now()]
            );
            return $sourceId;
        }

        // CENÁRIO 4: Revenda sem storage explícito -> Vincula ao Estoque de Armazenamento padrão (ex: Seco 1)
        $isResale = $product->type === 'resale' || (class_exists(\App\Enums\ProductType::class) && $product->type === \App\Enums\ProductType::RESALE->value);
        if ($isResale) {
            $defaultStorage = DB::table('storage_locations')
                ->where('type', 'storage')
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->orderBy('sort_order')
                ->first();

            if ($defaultStorage) {
                DB::table('product_storage_locations')->updateOrInsert(
                    ['product_id' => $product->id, 'storage_location_id' => $defaultStorage->id],
                    ['updated_at' => now()]
                );

                if ($displayLoc && empty($displayLoc->source_location_id)) {
                    DB::table('product_storage_locations')
                        ->where('product_id', $product->id)
                        ->where('storage_location_id', $displayLoc->storage_location_id)
                        ->update(['source_location_id' => $defaultStorage->id, 'updated_at' => now()]);
                }

                return (int) $defaultStorage->id;
            }
        }

        return $locations->isNotEmpty() ? (int) $locations->first()->storage_location_id : null;
    }

    public function calculateSmartConversion(?int $productId, ?int $supplierId, ?string $currentXmlName, ?string $xmlUomRaw): array
    {
        $result = [
            'factor' => 1.0,
            'source' => 'manual',
            'notification' => null,
        ];

        if (!$productId || !$currentXmlName) {
            return $result;
        }

        if ($supplierId) {
            $lastPurchase = ProductCostHistory::where('supplier_id', $supplierId)
                ->where('product_id', $productId)
                ->where('xml_name', $currentXmlName)
                ->latest('id')
                ->first();

            if ($lastPurchase && $lastPurchase->conversion_factor) {
                $factor = (float) $lastPurchase->conversion_factor;
                $result['factor'] = $factor;
                $result['source'] = 'history';
                return $result;
            }
        }

        $product = Product::find($productId);
        $internalUom = strtoupper((string) ($product?->unit_of_measure ?? ''));
        $xmlUom = strtoupper(preg_replace('/[^A-Z]/', '', (string) ($xmlUomRaw ?? '')));

        $isIdentical = ($xmlUom === $internalUom)
            || (in_array($xmlUom, ['KG', 'KIL']) && $internalUom === 'KG')
            || (in_array($xmlUom, ['L', 'LT', 'LIT']) && $internalUom === 'LT')
            || (in_array($xmlUom, ['UN', 'UND']) && $internalUom === 'UN');

        if ($isIdentical) {
            $result['factor'] = 1.0;
            $result['source'] = 'identical';
            return $result;
        }

        $factor = 1.0;
        $autoMath = false;

        if (in_array($xmlUom, ['KG', 'KIL']) && $internalUom === 'G') {
            $factor = 1000.0;
            $autoMath = true;
        } elseif ($xmlUom === 'G' && in_array($internalUom, ['KG', 'KIL'])) {
            $factor = 0.001;
            $autoMath = true;
        } elseif (in_array($xmlUom, ['L', 'LT', 'LIT']) && $internalUom === 'ML') {
            $factor = 1000.0;
            $autoMath = true;
        } elseif ($xmlUom === 'ML' && in_array($internalUom, ['L', 'LT', 'LIT'])) {
            $factor = 0.001;
            $autoMath = true;
        }

        if ($autoMath) {
            $result['factor'] = $factor;
            $result['source'] = 'auto_math';
        }

        return $result;
    }

    public function processDirectItem(array $item, ?string $invoiceKey, ?int $supplierId, bool $autoApprove = false): void
    {
        $processQty = (float) ($item['xml_qty'] ?? 0);
        $invoiceCost = (float) ($item['xml_cost'] ?? 0);
        $invoiceUom = (string) ($item['xml_uom'] ?? '');
        $destination = $item['destination_type'] ?? (!empty($item['is_personal']) ? 'personal' : 'stock');
        $receiptItemId = $item['goods_receipt_item_id'] ?? null;

        // 🚀 ROTA 1: USO PESSOAL
        if ($destination === 'personal') {
            PersonalPurchase::create([
                'nfe_key'    => $invoiceKey,
                'supplier_id'=> $supplierId,
                'xml_name'   => $item['xml_name'],
                'quantity'   => $processQty,
                'unit_cost'  => $invoiceCost,
                'total_cost' => round($processQty * $invoiceCost, 4),
                'auto_approve' => (bool) $autoApprove,
            ]);
            return;
        }

        // 🚀 ROTA 2: PATRIMÔNIO / ATIVO IMOBILIZADO (Não toca no Kardex!)
        if ($destination === 'asset') {
            CompanyAsset::create([
                'name'             => !empty($item['asset_name']) ? $item['asset_name'] : $item['xml_name'],
                'asset_tag'        => !empty($item['asset_tag']) ? $item['asset_tag'] : null,
                'location'         => !empty($item['asset_location']) ? $item['asset_location'] : null,
                'description'      => "Adquirido via NF-e {$invoiceKey} - Item XML: {$item['xml_name']}",
                'acquisition_cost' => round($processQty * $invoiceCost, 2),
                'acquisition_date' => now()->toDateString(),
                'warranty_until'   => !empty($item['warranty_until']) ? $item['warranty_until'] : null,
                'supplier_id'      => $supplierId,
                'nfe_key'          => $invoiceKey,
                'status'           => 'active',
            ]);
            return;
        }

        // 🚀 ROTA 3: ESTOQUE / INSUMO / REVENDA
        $productId = $item['product_id'];
        $conversionFactor = (float) ($item['conversion_factor'] ?? 1.0);
        if ($conversionFactor <= 0) $conversionFactor = 1.0;

        $actualQty = round($processQty * $conversionFactor, 4);
        $actualUnitCost = round($invoiceCost / $conversionFactor, 5);

        $product = Product::find($productId);
        $inventoryService = app(InventoryService::class);

        if ($product) {
            $product->updateQuietly(['base_cost' => $actualUnitCost]);

            if ($receiptItemId) {
                $receiptItem = GoodsReceiptItem::find($receiptItemId);
                if ($receiptItem) {
                    $receiptQty = (float) $receiptItem->quantity;
                    $delta = round($actualQty - $receiptQty, 4);

                    if ($delta > 0) {
                        $targetLocationId = $this->resolveStorageLocationForProcurement($product); $inventoryService->addStock($product->fresh(), $delta, 'nfe_reconcile_surplus', null, $targetLocationId);
                    } elseif ($delta < 0) {
                        $inventoryService->deductStock($product->fresh(), abs($delta));
                    }

                    $receiptItem->update([
                        'status'         => 'reconciled',
                        'reconciled_qty' => $actualQty,
                        'nfe_key'        => $invoiceKey,
                    ]);

                    $receipt = $receiptItem->goodsReceipt;
                    if ($receipt) {
                        $hasPending = $receipt->items()->where('status', '!=', 'reconciled')->exists();
                        if (!$hasPending) {
                            $receipt->update([
                                'status'  => 'reconciled',
                                'nfe_key' => $invoiceKey,
                            ]);
                        }
                    }
                }
            } else {
                $targetLocationId = $this->resolveStorageLocationForProcurement($product);
            $inventoryService->addStock($product->fresh(), $actualQty, 'nfe_import', null, $targetLocationId);
            }
        }

        ProductCostHistory::create([
            'product_id'               => $productId,
            'supplier_id'              => $supplierId,
            'nfe_key'                  => $invoiceKey,
            'xml_name'                 => $item['xml_name'],
            'quantity'                 => $actualQty,
            'unit_cost'                => $actualUnitCost,
            'conversion_factor'        => $conversionFactor,
            'original_quantity'        => $processQty,
            'original_unit_cost'       => $invoiceCost,
            'original_unit_of_measure' => $invoiceUom,
            'auto_approve'             => $autoApprove,
        ]);
    }

    public function processConfirmedItems(array $payload, array $currentItems): array
    {
        $processQty = (float) ($payload['xml_qty'] ?? 0);
        $availableQty = (float) ($payload['available_qty'] ?? 0);

        if ($processQty <= 0 || $processQty > $availableQty) {
            throw new InvalidArgumentException("A quantidade informada ({$processQty}) deve ser maior que zero e menor ou igual a {$availableQty}.");
        }

        $supplierId = isset($payload['supplier_id']) && !empty($payload['supplier_id'])
            ? (int) $payload['supplier_id']
            : null;

        if (!$supplierId) {
            throw new InvalidArgumentException('O fornecedor é obrigatório para processar itens da nota.');
        }

        $destination = $payload['destination_type'] ?? (!empty($payload['is_personal']) ? 'personal' : 'stock');
        $productId = isset($payload['product_id']) && !empty($payload['product_id'])
            ? (int) $payload['product_id']
            : null;

        if ($destination === 'stock' && !$productId) {
            throw new InvalidArgumentException('Selecione um produto cadastrado para entrada em estoque.');
        }

        $invoiceKey = $payload['invoice_key'] ?? null;
        $targetXmlName = (string) ($payload['xml_name'] ?? '');
        $conversionFactor = (float) ($payload['conversion_factor'] ?? 1.0);
        $uuidToFind = (string) ($payload['item_uuid'] ?? '');
        $receiptItemId = $payload['goods_receipt_item_id'] ?? null;

        $itemData = [
            'xml_qty'               => $processQty,
            'xml_cost'              => (float) ($payload['xml_cost'] ?? 0),
            'xml_uom'               => (string) ($payload['xml_uom'] ?? ''),
            'destination_type'      => $destination,
            'is_personal'           => $destination === 'personal',
            'xml_name'              => $targetXmlName,
            'product_id'            => $productId,
            'goods_receipt_item_id' => $receiptItemId,
            'conversion_factor'     => $conversionFactor,
            'asset_name'            => $payload['asset_name'] ?? null,
            'asset_location'        => $payload['asset_location'] ?? null,
            'asset_tag'             => $payload['asset_tag'] ?? null,
            'warranty_until'        => $payload['warranty_until'] ?? null,
        ];

        return DB::transaction(function () use ($itemData, $invoiceKey, $supplierId, $currentItems, $uuidToFind, $processQty, $availableQty, $targetXmlName, $destination, $productId, $conversionFactor, $receiptItemId) {

            $this->processDirectItem($itemData, $invoiceKey, $supplierId, false);

            $autoProcessedCount = 0;
            $items = $currentItems;

            foreach ($items as $key => $item) {
                if ((isset($item['item_uuid']) && (string)$item['item_uuid'] === $uuidToFind) || (string)$key === $uuidToFind) {
                    if ($processQty < $availableQty) {
                        $newAvailable = round($availableQty - $processQty, 4);
                        $items[$key]['available_qty'] = $newAvailable;
                        $items[$key]['xml_qty'] = $newAvailable;
                        $items[$key]['destination_type'] = $destination;
                        $items[$key]['is_personal'] = ($destination === 'personal');
                        $items[$key]['factor_source'] = 'manual';
                    } else {
                        unset($items[$key]);
                    }
                    continue;
                }

                // Efeito dominó para itens idênticos
                if (!$receiptItemId && isset($item['xml_name']) && (string)$item['xml_name'] === $targetXmlName) {
                    $duplicateItemData = array_merge($itemData, [
                        'xml_qty'  => (float) ($item['available_qty'] ?? $item['xml_qty'] ?? 0),
                        'xml_cost' => (float) ($item['xml_cost'] ?? 0),
                        'xml_uom'  => (string) ($item['xml_uom'] ?? ''),
                    ]);

                    $this->processDirectItem($duplicateItemData, $invoiceKey, $supplierId, false);
                    unset($items[$key]);
                    $autoProcessedCount++;
                }
            }

            return [
                'updated_items'        => $items,
                'auto_processed_count' => $autoProcessedCount,
            ];
        });
    }
}
