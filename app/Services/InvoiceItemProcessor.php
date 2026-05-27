<?php

namespace App\Services;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\PersonalPurchase;

class InvoiceItemProcessor
{
    public function applySmartConversion($state, Set $set, Get $get): void
    {
        $set('factor_source', 'manual');
        if (! $state) return;

        $supplierId = $get('../../supplier_id');
        $currentXmlName = $get('xml_name'); // 👈 Pegamos o nome exato do item nesta linha

        if (! $supplierId || ! $currentXmlName) return;

        // 🔥 AGORA COM FILTRO DE NOME EXATO
        $lastPurchase = ProductCostHistory::where('supplier_id', $supplierId)
            ->where('product_id', $state)
            ->where('xml_name', $currentXmlName) // 👈 A camada extra de inteligência aqui!
            ->latest('id')
            ->first();

        if ($lastPurchase && $lastPurchase->conversion_factor) {
            $set('conversion_factor', $lastPurchase->conversion_factor);
            $set('factor_source', 'history');
            Notification::make()
                ->title('Fator recuperado!')
                ->body("O sistema puxou o fator de {$lastPurchase->conversion_factor} do seu histórico.")
                ->success()
                ->send();
            return;
        }

        $product = Product::find($state);
        $internalUom = strtoupper($product?->unit_of_measure ?? '');
        $xmlUom = strtoupper(preg_replace('/[^A-Z]/', '', $get('xml_uom') ?? ''));

        $isIdentical = false;
        if ($xmlUom === $internalUom) {
            $isIdentical = true;
        } elseif (in_array($xmlUom, ['KG', 'KIL']) && $internalUom === 'KG') {
            $isIdentical = true;
        } elseif (in_array($xmlUom, ['L', 'LT', 'LIT']) && $internalUom === 'LT') {
            $isIdentical = true;
        } elseif (in_array($xmlUom, ['UN', 'UND']) && $internalUom === 'UN') {
            $isIdentical = true;
        }

        if ($isIdentical) {
            $set('conversion_factor', 1);
            $set('factor_source', 'identical');
            Notification::make()
                ->title('Unidades Idênticas!')
                ->body("Tanto a nota quanto o estoque usam {$internalUom}. O fator 1 foi mantido.")
                ->success()
                ->send();
            return;
        }

        $factor = 1;
        $autoMath = false;
        $mathMsg = '';

        if (in_array($xmlUom, ['KG', 'KIL']) && $internalUom === 'G') {
            $factor = 1000;
            $autoMath = true;
            $mathMsg = 'KG para Gramas (x1000)';
        } elseif ($xmlUom === 'G' && in_array($internalUom, ['KG', 'KIL'])) {
            $factor = 0.001;
            $autoMath = true;
            $mathMsg = 'Gramas para KG (/1000)';
        } elseif (in_array($xmlUom, ['L', 'LT', 'LIT']) && $internalUom === 'ML') {
            $factor = 1000;
            $autoMath = true;
            $mathMsg = 'Litros para ML (x1000)';
        } elseif ($xmlUom === 'ML' && in_array($internalUom, ['L', 'LT', 'LIT'])) {
            $factor = 0.001;
            $autoMath = true;
            $mathMsg = 'ML para Litros (/1000)';
        }

        if ($autoMath) {
            $set('conversion_factor', $factor);
            $set('factor_source', 'auto_math');
            Notification::make()
                ->title('Conversão Automática Aplicada!')
                ->body("O sistema detectou uma conversão de {$mathMsg} e ajustou o fator para você.")
                ->info()
                ->send();
        }
    }

    public function processConfirmedItem(Get $get, Set $set, \Livewire\Component $livewire): void
    {
        $processQty = (float) $get('xml_qty');
        $availableQty = (float) $get('available_qty');

        if ($processQty <= 0 || $processQty > $availableQty) {
            Notification::make()->title('Quantidade Inválida!')->warning()->send();
            $set('xml_qty', $availableQty);
            $set('confirmation_step', 0);
            return;
        }

        if ($get('confirmation_step') == 0) {
            $set('confirmation_step', 1);
            return;
        }

        $invoiceCost = (float) $get('xml_cost');
        $invoiceUom = $get('xml_uom');
        $isPersonal = $get('is_personal');

        DB::transaction(function () use ($isPersonal, $processQty, $invoiceCost, $invoiceUom, $get) {
            if ($isPersonal) {
                PersonalPurchase::create([
                    'nfe_key' => $get('../../invoice_key'),
                    'supplier_id' => $get('../../supplier_id'),
                    'xml_name' => $get('xml_name'),
                    'quantity' => $processQty,
                    'unit_cost' => $invoiceCost,
                    'total_cost' => $processQty * $invoiceCost,
                ]);
            } else {
                $productId = $get('product_id');
                $conversionFactor = (float) ($get('conversion_factor') ?? 1);
                $actualQty = $processQty * $conversionFactor;
                $actualUnitCost = $invoiceCost / $conversionFactor;

                $product = Product::find($productId);
                $product->increment('current_stock', $actualQty);
                $product->update(['base_cost' => $actualUnitCost]);

                ProductCostHistory::create([
                    'product_id' => $productId,
                    'supplier_id' => $get('../../supplier_id'),
                    'nfe_key' => $get('../../invoice_key'),
                    'xml_name' => $get('xml_name'),
                    'quantity' => $actualQty,
                    'unit_cost' => $actualUnitCost,
                    'conversion_factor' => $conversionFactor,
                    'original_quantity' => $processQty,
                    'original_unit_cost' => $invoiceCost,
                    'original_unit_of_measure' => $invoiceUom,
                ]);
            }
        });

        $uuidToFind = $get('item_uuid');
        $items = $livewire->data['xml_items'] ?? [];

        foreach ($items as $uuid => $item) {
            if (isset($item['item_uuid']) && $item['item_uuid'] === $uuidToFind) {
                if ($processQty < $availableQty) {
                    $newAvailable = $availableQty - $processQty;
                    $items[$uuid]['available_qty'] = $newAvailable;
                    $items[$uuid]['xml_qty'] = $newAvailable;
                    $items[$uuid]['confirmation_step'] = 0;
                    $items[$uuid]['is_personal'] = false;
                    $items[$uuid]['factor_source'] = 'manual';
                } else {
                    unset($items[$uuid]);
                }
                break;
            }
        }

        $livewire->data['xml_items'] = $items;
        Notification::make()->title('Item processado!')->success()->send();

        // CORRIGIDO: Este "grito" avisa os Widgets lá de baixo para eles buscarem no banco!
        $livewire->dispatch('items-processed');
    }
}
