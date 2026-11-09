<?php

namespace App\Services\Purchases;

use App\Models\ProductCostHistory;
use Illuminate\Support\Facades\DB;

class CostRectificationService
{
    /**
     * Heurística inteligente para identificar compras suspeitas de erro de conversão
     */
    public function isSuspect(ProductCostHistory $record): bool
    {
        $origUom = strtoupper(trim(preg_replace('/[^A-Z]/', '', (string) $record->original_unit_of_measure)));
        $prodUom = strtoupper(trim(preg_replace('/[^A-Z]/', '', (string) ($record->product?->unit_of_measure ?? ''))));
        $factor  = (float) ($record->conversion_factor ?: 1);

        $normalize = fn (string $u) => match ($u) {
            'G', 'GR', 'GRAMA', 'GRAMAS'        => 'G',
            'KG', 'KIL', 'KILO', 'QUILO'        => 'KG',
            'L', 'LT', 'LIT', 'LITRO', 'LITROS' => 'L',
            'ML', 'MILILITRO'                   => 'ML',
            'UN', 'UND', 'UNID', 'UNIDADE'      => 'UN',
            'CX', 'CXA', 'CAIXA'                => 'CX',
            'FD', 'FDO', 'FARDO'                => 'FD',
            'PCT', 'PC', 'PACOTE'               => 'PCT',
            default                             => $u,
        };

        $normOrig = $normalize($origUom);
        $normProd = $normalize($prodUom);

        // 1. Embalagem coletiva (CX, FD, PCT) sem conversão (fator <= 1)
        $packageUoms = ['CX', 'FD', 'PCT', 'SC'];
        if (in_array($normOrig, $packageUoms) && !in_array($normProd, $packageUoms) && $factor <= 1.0) {
            return true;
        }

        // 2. Unidade avulsa lançada para peso/volume (UN -> G/ML) sem conversão
        if ($normOrig === 'UN' && in_array($normProd, ['G', 'ML']) && $factor <= 1.0) {
            return true;
        }

        // 3. Mesma unidade na nota e no cadastro, mas com fator diferente de 1
        if (!empty($normOrig) && $normOrig === $normProd && abs($factor - 1.0) > 0.001) {
            return true;
        }

        // 4. Desvio desproporcional frente ao custo base de referência
        $baseCost = (float) ($record->product?->base_cost ?? 0);
        $unitCost = (float) $record->unit_cost;

        if ($baseCost > 0 && $unitCost > 0) {
            $ratio = $unitCost / $baseCost;
            if ($ratio >= 1.8 || $ratio <= 0.45) {
                return true;
            }
        }

        return false;
    }

    /**
     * Simula os valores resultantes da alteração do fator
     */
    public function calculatePreview(ProductCostHistory $record, float $newFactor): array
    {
        if ($newFactor <= 0) {
            $newFactor = 1.0;
        }

        $origQty = (float) ($record->original_quantity ?: $record->quantity);
        $origCost = (float) ($record->original_unit_cost ?: ($record->unit_cost * ($record->conversion_factor ?: 1)));

        $newQty = round($origQty * $newFactor, 4);
        $newCost = round($origCost / $newFactor, 5);
        $deltaQty = round($newQty - (float) $record->quantity, 4);

        return [
            'original_quantity'  => $origQty,
            'original_unit_cost' => $origCost,
            'new_quantity'       => $newQty,
            'new_unit_cost'      => $newCost,
            'delta_quantity'     => $deltaQty,
            'factor'             => $newFactor,
        ];
    }

    /**
     * Executa a retificação persistindo os ajustes e conciliando o estoque
     */
    public function rectify(ProductCostHistory $record, float $newFactor, bool $adjustInventory = true): array
    {
        $preview = $this->calculatePreview($record, $newFactor);

        return DB::transaction(function () use ($record, $adjustInventory, $preview) {
            $record->update([
                'conversion_factor'  => $preview['factor'],
                'quantity'           => $preview['new_quantity'],
                'unit_cost'          => $preview['new_unit_cost'],
                'original_quantity'  => $preview['original_quantity'],
                'original_unit_cost' => $preview['original_unit_cost'],
            ]);

            $product = $record->product;

            if ($adjustInventory && $product && $preview['delta_quantity'] != 0) {
                $product->increment('current_stock', $preview['delta_quantity']);
            }

            $latest = ProductCostHistory::where('product_id', $record->product_id)->latest('id')->first();
            if ($latest && $latest->id === $record->id && $product) {
                $product->updateQuietly(['base_cost' => $preview['new_unit_cost']]);
            }

            return $preview;
        });
    }
}
