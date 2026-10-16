<?php

namespace App\Services\Printing;

use Illuminate\Support\Str;

class OrderTableBuilder
{
    public static function hasDecimalQuantity(iterable $items, callable $getQty): bool
    {
        foreach ($items as $item) {
            $qty = (float) $getQty($item);
            if (floor($qty) != $qty) {
                return true;
            }
        }
        return false;
    }

    public static function formatQty(float $qty, bool $hasDecimal): string
    {
        if ($hasDecimal) {
            return number_format($qty, 1, ',', '');
        }
        return (string) round($qty);
    }

    /**
     * 1. CONSTRUTOR DA TABELA FISCAL (NFC-e)
     */
    public static function buildFiscal(
        BlueprintBuilder $b,
        $detList,
        int|string $columnsOrFont = 'A',
        int $paperWidth = 80
    ): void {
        $cols = ReceiptFormatter::resolveColumns($columnsOrFont, $paperWidth);
        $isFontB = ($cols >= 60);

        $hasDecimal = self::hasDecimalQuantity($detList, fn($det) => (float) $det->prod->qCom);

        $codSize  = $isFontB ? 4 : 3;
        $qtySize  = $hasDecimal ? 4 : ($isFontB ? 3 : 2);
        $unSize   = 2;
        $vUnSize  = $isFontB ? 8 : 7;
        $vTotSize = $isFontB ? 9 : 8;

        $headers = ['COD', ' ', 'DESCRICAO', ' ', 'QTD', ' ', 'UN', ' ', 'VL UN', ' ', 'VL TOT'];
        $sizes   = [$codSize, 1, 0, 1, $qtySize, 1, $unSize, 1, $vUnSize, 1, $vTotSize];
        $aligns  = ['L', 'L', 'L', 'L', 'R', 'L', 'L', 'L', 'R', 'L', 'R'];

        $fixedTotal = $codSize + 1 + 1 + $qtySize + 1 + $unSize + 1 + $vUnSize + 1 + $vTotSize;
        $descSize   = max(10, $cols - $fixedTotal);

        $b->text(ReceiptFormatter::columns($headers, $sizes, $aligns, $cols), true);
        $b->text(ReceiptFormatter::divider($cols));

        foreach ($detList as $det) {
            $prod = $det->prod;

            $cProd = substr(trim((string) $prod->cProd), 0, $codSize);
            $xProd = substr(trim(strtoupper(Str::ascii((string) $prod->xProd))), 0, $descSize);

            $qty = (float) $prod->qCom;
            $qCom = substr(self::formatQty($qty, $hasDecimal), 0, $qtySize);

            $un = substr(trim(strtoupper((string) $prod->uCom)), 0, $unSize);
            $vUnCom = substr(number_format((float) $prod->vUnCom, 2, ',', ''), 0, $vUnSize);
            $vProd = substr(number_format((float) $prod->vProd, 2, ',', ''), 0, $vTotSize);

            $row = [$cProd, ' ', $xProd, ' ', $qCom, ' ', $un, ' ', $vUnCom, ' ', $vProd];

            $b->text(ReceiptFormatter::columns($row, $sizes, $aligns, $cols));
        }
    }

    /**
     * 2. CONSTRUTOR DA TABELA DE CONFERÊNCIA (Recibo de Salão / Balcão)
     */
    public static function buildConference(
        BlueprintBuilder $b,
        $items,
        int|string $columnsOrFont = 'A',
        int $paperWidth = 80
    ): void {
        $activeItems = collect($items)->filter(function ($item) {
            return ($item->status ?? null) !== 'cancelled';
        });

        $cols = ReceiptFormatter::resolveColumns($columnsOrFont, $paperWidth);
        $hasDecimal = self::hasDecimalQuantity($activeItems, fn($item) => (float) $item->quantity);

        $qtySize = $hasDecimal ? 6 : 4;
        $priceSize = 10;
        $fixedTotal = $qtySize + 1 + 1 + $priceSize;
        $descSize = max(15, $cols - $fixedTotal);

        $headers = ['QTD', ' ', 'DESCRICAO', ' ', 'VALOR R$'];
        $sizes   = [$qtySize, 1, 0, 1, $priceSize];
        $aligns  = ['L', 'L', 'L', 'L', 'R'];

        $b->text(ReceiptFormatter::columns($headers, $sizes, $aligns, $cols), true);
        $b->text(ReceiptFormatter::divider($cols));

        $parents = $activeItems->filter(fn($item) => empty($item->parent_item_id));
        $children = $activeItems->filter(fn($item) => !empty($item->parent_item_id));

        foreach ($parents as $parent) {
            $qty = (float) $parent->quantity;
            $qtyStr = substr(self::formatQty($qty, $hasDecimal) . 'x', 0, $qtySize);

            $rawName = trim(strtoupper(Str::ascii($parent->product_name)));
            if (!empty($parent->is_reward)) {
                $rawName .= ' [CORTESIA]';
            }
            $pName = substr($rawName, 0, $descSize);
            $totalItem = substr(number_format((float) $parent->total_price, 2, ',', '.'), 0, $priceSize);

            $b->text(ReceiptFormatter::columns(
                [$qtyStr, ' ', $pName, ' ', $totalItem],
                $sizes,
                $aligns,
                $cols
            ), true);

            self::buildModifiersAndNotes($b, $parent, $qtySize + 1, $descSize, $priceSize, 'R', $cols);

            $itemChildren = $children->filter(fn($c) => (int)$c->parent_item_id === (int)$parent->id);
            if ($itemChildren->isEmpty() && method_exists($parent, 'relationLoaded') && $parent->relationLoaded('subItems')) {
                $itemChildren = $parent->subItems->filter(fn($c) => ($c->status ?? null) !== 'cancelled');
            }

            foreach ($itemChildren as $child) {
                $childName = '    ' . trim(strtoupper(Str::ascii($child->product_name)));
                $cDesc = substr($childName, 0, $descSize);

                $cTotal = ((float)$child->total_price > 0)
                    ? substr(number_format((float)$child->total_price, 2, ',', '.'), 0, $priceSize)
                    : '';

                $b->text(ReceiptFormatter::columns(
                    ['', ' ', $cDesc, ' ', $cTotal],
                    $sizes,
                    $aligns,
                    $cols
                ), false);

                self::buildModifiersAndNotes($b, $child, $qtySize + 1 + 4, $descSize - 4, $priceSize, 'R', $cols);
            }
        }
    }

    /**
     * 3. CONSTRUTOR DO CHECKLIST DO MOTOBOY (Delivery)
     */
    public static function buildDispatch(
        BlueprintBuilder $b,
        $items,
        int|string $columnsOrFont = 'A',
        int $paperWidth = 80
    ): void {
        $activeItems = collect($items)->filter(function ($item) {
            return ($item->status ?? null) !== 'cancelled';
        });

        $cols = ReceiptFormatter::resolveColumns($columnsOrFont, $paperWidth);
        $hasDecimal = self::hasDecimalQuantity($activeItems, fn($item) => (float) $item->quantity);

        $chkSize = 3;
        $qtySize = $hasDecimal ? 6 : 4;
        $fixedTotal = $chkSize + 1 + $qtySize + 1;
        $descSize = max(15, $cols - $fixedTotal);

        $headers = ['CHK', ' ', 'QTD', ' ', 'DESCRICAO'];
        $sizes   = [$chkSize, 1, $qtySize, 1, 0];
        $aligns  = ['L', 'L', 'L', 'L', 'L'];

        $b->text(ReceiptFormatter::columns($headers, $sizes, $aligns, $cols), true);
        $b->text(ReceiptFormatter::divider($cols));

        $parents = $activeItems->filter(fn($item) => empty($item->parent_item_id));
        $children = $activeItems->filter(fn($item) => !empty($item->parent_item_id));

        foreach ($parents as $parent) {
            $qty = (float) $parent->quantity;
            $qtyStr = substr(self::formatQty($qty, $hasDecimal) . 'x', 0, $qtySize);

            $rawName = trim(strtoupper(Str::ascii($parent->product_name)));
            if (!empty($parent->is_reward)) {
                $rawName .= ' [CORTESIA]';
            }
            $pName = substr($rawName, 0, $descSize);

            $itemChildren = $children->filter(fn($c) => (int)$c->parent_item_id === (int)$parent->id);
            if ($itemChildren->isEmpty() && method_exists($parent, 'relationLoaded') && $parent->relationLoaded('subItems')) {
                $itemChildren = $parent->subItems->filter(fn($c) => ($c->status ?? null) !== 'cancelled');
            }
            $isComboParent = $itemChildren->isNotEmpty();

            if ($isComboParent) {
                $b->text(ReceiptFormatter::columns(
                    ['   ', ' ', $qtyStr, ' ', $pName],
                    $sizes,
                    $aligns,
                    $cols
                ), true);
            } else {
                $b->text(ReceiptFormatter::columns(
                    ['[ ]', ' ', $qtyStr, ' ', $pName],
                    $sizes,
                    $aligns,
                    $cols
                ), true);
            }

            self::buildModifiersAndNotes($b, $parent, $chkSize + 1 + $qtySize + 1, $descSize, 0, 'L', $cols);

            if ($isComboParent) {
                foreach ($itemChildren as $child) {
                    $cQty = (float) $child->quantity;
                    $cQtyStr = substr(self::formatQty($cQty, $hasDecimal) . 'x', 0, $qtySize);
                    $cName = '   └ ' . trim(strtoupper(Str::ascii($child->product_name)));
                    $cDesc = substr($cName, 0, $descSize);

                    $b->text(ReceiptFormatter::columns(
                        ['[ ]', ' ', $cQtyStr, ' ', $cDesc],
                        $sizes,
                        $aligns,
                        $cols
                    ), false);

                    self::buildModifiersAndNotes($b, $child, $chkSize + 1 + $qtySize + 1 + 5, $descSize - 5, 0, 'L', $cols);
                }
            }
        }
    }

    private static function buildModifiersAndNotes(
        BlueprintBuilder $b,
        $item,
        int $leftOffset,
        int $descSize,
        int $rightOffset,
        string $rightAlign,
        int $cols = 48
    ): void {
        $rawMods = $item->modifiers ?? $item->structured_modifiers ?? $item->structuredModifiers ?? [];

        if (!empty($rawMods)) {
            $itemQty = max(1, (float)($item->quantity ?? 1));

            foreach ($rawMods as $mod) {
                $modObj = is_array($mod) ? (object) $mod : $mod;
                $cleanName = substr(trim(preg_replace('/^[\s•+\-]+/', '', strtoupper(Str::ascii($modObj->name ?? '')))), 0, $descSize);
                if (str_contains($cleanName, '[VIAGEM]')) continue;

                $modQty = (float)($modObj->quantity ?? $modObj->qty ?? 1);

                // 🚀 DETECÇÃO INTELIGENTE DE INGREDIENTE / OPÇÃO
                $type = strtolower((string)($modObj->type ?? $modObj->modifier_type ?? $modObj->group_type ?? ''));
                $isIngredient = in_array($type, ['ingredient', 'ingrediente', 'option', 'opcao', 'flavor', 'sabor']);

                // Quantidade do modificador por unidade do produto pai
                $unitQty = $itemQty > 0 ? ($modQty / $itemQty) : $modQty;

                // Ingredientes NUNCA exibem multiplicador. Adicionais só exibem se forem > 1 por unidade.
                if ($isIngredient || $unitQty <= 1 || $modQty == $itemQty) {
                    $prefix = '';
                } else {
                    $displayQty = (floor($unitQty) == $unitQty) ? (int)$unitQty : (int)$modQty;
                    $prefix = ($displayQty > 1) ? ($displayQty . 'X ') : '';
                }

                $sign = (str_starts_with($cleanName, 'SEM ') || str_starts_with($cleanName, 'NAO ') || str_starts_with($cleanName, 'SEM_')) ? '- ' : '+ ';

                if ($rightOffset > 0) {
                    $b->text(ReceiptFormatter::columns(['', ' ', '  ' . $sign . $prefix . $cleanName, ' ', ''], [$leftOffset - 1, 1, 0, 1, $rightOffset], ['L', 'L', 'L', 'L', $rightAlign], $cols), false);
                } else {
                    $b->text(ReceiptFormatter::columns(['', '  ' . $sign . $prefix . $cleanName], [$leftOffset, 0], ['L', 'L'], $cols), false);
                }
            }
        }

        if (!empty($item->notes)) {
            $cleanNotes = substr(strtoupper(Str::ascii($item->notes)), 0, max(5, $descSize - 7));
            if ($rightOffset > 0) {
                $b->text(ReceiptFormatter::columns(['', ' ', '  (OBS: ' . $cleanNotes . ')', ' ', ''], [$leftOffset - 1, 1, 0, 1, $rightOffset], ['L', 'L', 'L', 'L', $rightAlign], $cols), false);
            } else {
                $b->text(ReceiptFormatter::columns(['', '  (OBS: ' . $cleanNotes . ')'], [$leftOffset, 0], ['L', 'L'], $cols), false);
            }
        }
    }
}
