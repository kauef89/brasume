<?php

namespace App\Support;

use App\Models\Product;

class ProductFormatter
{
    /**
     * Retorna a pílula HTML rica para exibir dentro do Dropdown aberto.
     */
    public static function getHtmlLabel(Product $product): string
    {
        $categoryName = $product->category?->name ?? 'Sem categoria';

        $typeLabel = match($product->type) {
            'ingredient' => 'Insumos',
            'resale' => 'Revendas',
            'packaging' => 'Embalagens',
            'consumable' => 'Consumos',
            'dish' => 'Pratos',
            'combo' => 'Combos',
            'manufactured' => 'Preparados',
            default => 'Outros',
        };

        return "
            <div style='display: flex; justify-content: space-between; align-items: center; width: 100%;'>
                <span style='font-weight: 700; color: var(--text-color);'>{$product->name}</span>
                <span style='font-size: 0.70rem; font-weight: 600; text-transform: uppercase; color: #9ca3af; margin-left: 0.5rem; text-overflow: ellipsis; white-space: nowrap; overflow: hidden;'>
                    {$typeLabel} &rsaquo; {$categoryName}
                </span>
            </div>
        ";
    }

    /**
     * Retorna o texto limpo para exibir quando o item já está selecionado na caixa.
     */
    public static function getCleanLabel(Product $product): string
    {
        $categoryName = $product->category?->name ?? 'Sem categoria';

        return "{$product->name} — ({$categoryName})";
    }
}
