<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * @mixin \Filament\Resources\Resource
 */
trait HasGlobalSearch
{
    // 1. QUAIS COLUNAS O FILAMENT DEVE PESQUISAR?
    public static function getGloballySearchableAttributes(): array
    {
        $model = static::getModel();

        if ($model === \App\Models\Customer::class || $model === \App\Models\Employee::class) {
            return ['name', 'cpf', 'phone', 'email'];
        }

        if ($model === \App\Models\Supplier::class) {
            return ['name', 'document_name', 'document'];
        }

        return ['name'];
    }

    // 2. PARA ONDE IR AO CLICAR NO RESULTADO?
    public static function getGlobalSearchResultUrl(Model $record): string
    {
        if (static::hasPage('view')) {
            return static::getUrl('view', ['record' => $record]);
        }

        return static::getUrl('edit', ['record' => $record]);
    }

    // ==============================================================
    // 🚀 A MÁGICA VISUAL: Título e Breadcrumb na mesma linha
    // ==============================================================
    public static function getGlobalSearchResultTitle(Model $record): string | HtmlString
    {
        $name = $record->name ?? 'Sem Nome';
        $breadcrumb = '';

        if ($record instanceof \App\Models\Product) {
            $typeLabel = match($record->type) {
                'ingredient' => 'Insumo',
                'resale' => 'Revenda',
                'packaging' => 'Embalagem',
                'consumable' => 'Consumo',
                'dish' => 'Prato',
                'combo' => 'Combo',
                'manufactured' => 'Preparado',
                default => 'Outros',
            };

            $categoryName = $record->category?->name ?? 'Sem categoria';
            $breadcrumb = "{$typeLabel} &rsaquo; {$categoryName}";
        }
        elseif ($record instanceof \App\Models\Supplier) {
            $breadcrumb = $record->type === 'virtual' ? 'Virtual' : 'Físico';
        }
        elseif ($record instanceof \App\Models\Category) {
            $breadcrumb = match($record->type) {
                'external' => 'Vendas & Cardápio',
                'internal' => 'Cozinha & Insumos',
                'supply' => 'Uso e Consumo',
                default => 'Outros',
            };
        }

        // 🚀 O TRUQUE DO CSS E DO FLEXBOX:
        // 1. O style alarga a caixa de pesquisa do Filament para não espremer o texto (max-width: 600px).
        // 2. O flex + justify-between empurra o breadcrumb todo para a direita.
        return new HtmlString("
            <style>
                .fi-global-search-results-ctn {
                    max-width: 600px !important;
                    width: 100% !important;
                }
            </style>
            <div style='display: flex; justify-content: space-between; align-items: center; width: 100%; gap: 1rem;'>
                <span style='font-weight: 800; color: var(--text-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>
                    {$name}
                </span>
                " . ($breadcrumb ? "<span style='font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: #9ca3af; letter-spacing: 0.05em; white-space: nowrap; flex-shrink: 0;'>{$breadcrumb}</span>" : "") . "
            </div>
        ");
    }

    // ==============================================================
    // 🚀 DETALHES ADICIONAIS (Ficam discretos abaixo da linha principal)
    // ==============================================================
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [];

        // Para os produtos, mantemos apenas o estoque na linha de baixo
        if ($record instanceof \App\Models\Product) {
            if ($record->track_inventory) {
                $stock = rtrim(rtrim(number_format((float) $record->current_stock, 3, ',', '.'), '0'), ',');
                if ($stock === '') $stock = '0';
                $details['Estoque'] = "{$stock} " . strtoupper($record->unit_of_measure);
            }
        }
        elseif ($record instanceof \App\Models\Supplier) {
            if ($record->document) $details['Doc'] = $record->document;
        }
        elseif ($record instanceof \App\Models\Customer || $record instanceof \App\Models\Employee) {
            if ($record->phone) $details['Tel'] = $record->phone;
            if ($record->cpf) $details['CPF'] = $record->cpf;
        }

        return $details;
    }
}
