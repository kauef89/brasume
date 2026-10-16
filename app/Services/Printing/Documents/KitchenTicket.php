<?php

namespace App\Services\Printing\Documents;

use App\Models\Order;
use App\Models\CompanySetting;
use App\Models\OrderItem;
use App\Models\ProductModifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class KitchenTicket
{
    protected array $blueprint = [];
    protected Order $order;
    protected Collection $items;
    protected string $printMode;
    protected bool $separateMultiples;
    protected bool $isReprint;

    public function __construct(Order $order, Collection $items, string $printMode = 'station', bool $separateMultiples = false, bool $isReprint = false)
    {
        $this->order = $order;
        $this->items = $items;
        $this->printMode = $printMode;
        $this->separateMultiples = $separateMultiples;
        $this->isReprint = $isReprint;
    }

    public function getJobType(): string
    {
        return 'kitchen_ticket';
    }

    public function getOrderId(): ?int
    {
        return $this->order->id ?? null;
    }

    private function cleanText(?string $text): string
    {
        if (!$text) return '';
        $text = Str::ascii($text);
        $text = mb_strtoupper($text);
        $text = str_replace(['[VIAGEM]', '(VIAGEM)'], '', $text);
        return trim($text);
    }

    public function build(): array
    {
        $itemsToProcess = $this->resolvePrintableItems();

        $groupIds = $itemsToProcess
            ->flatMap(function ($item) {
                $rawMods = $item->modifiers ?? $item->structured_modifiers ?? $item->structuredModifiers ?? [];
                return collect($rawMods)
                    ->map(fn($m) => is_array($m) ? (object)$m : $m)
                    ->pluck('modifier_group_id');
            })
            ->filter()
            ->unique()
            ->values();

        $modifierGroups = !empty($groupIds)
            ? ProductModifier::whereIn('id', $groupIds)->get()->keyBy('id')
            : collect();

        if ($this->printMode === 'item') {
            foreach ($itemsToProcess as $item) {
                $iterations = $this->separateMultiples ? (int)$item->quantity : 1;
                $qtyToPrint = $this->separateMultiples ? 1 : (float)$item->quantity;
                $showQty = !($qtyToPrint == 1);

                for ($i = 0; $i < $iterations; $i++) {
                    $this->buildHeader();
                    $this->buildItemBody($item, $qtyToPrint, $showQty, $modifierGroups);
                    $this->buildFooter();

                    $this->blueprint[] = ['cmd' => 'beep'];
                    $this->blueprint[] = ['cmd' => 'cut'];
                }
            }
        } else {
            $this->buildHeader();
            $lastIndex = count($itemsToProcess) - 1;

            foreach ($itemsToProcess as $index => $item) {
                $this->buildItemBody($item, (float)$item->quantity, true, $modifierGroups);
                if ($index !== $lastIndex) {
                    $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false];
                }
            }
            $this->buildFooter();

            $this->blueprint[] = ['cmd' => 'beep'];
            $this->blueprint[] = ['cmd' => 'cut'];
        }

        return $this->blueprint;
    }

    private function resolvePrintableItems(): Collection
    {
        $allItems = $this->order->relationLoaded('items')
            ? $this->order->items
            : $this->items;

        $parentIds = $allItems->pluck('parent_item_id')->filter()->unique()->toArray();
        $existingItemIds = $this->items->pluck('id')->filter()->toArray();
        $printable = collect();

        foreach ($this->items as $item) {
            $isComboParent = in_array($item->id, $parentIds)
                || ($item->relationLoaded('subItems') && $item->subItems->isNotEmpty())
                || (!empty($item->id) && $item->subItems()->exists());

            if ($isComboParent) {
                $subItems = $item->relationLoaded('subItems')
                    ? $item->subItems
                    : $item->subItems()->with('modifiers')->get();

                if ($subItems instanceof Collection && $subItems->isNotEmpty()) {
                    foreach ($subItems as $subItem) {
                        if (!in_array($subItem->id, $existingItemIds)) {
                            $subItem->_combo_name = $item->product_name;
                            $printable->push($subItem);
                        }
                    }
                }
                continue;
            }

            if (!empty($item->parent_item_id) && empty($item->_combo_name)) {
                $parent = $allItems->firstWhere('id', $item->parent_item_id)
                    ?? (method_exists($item, 'parentItem') ? $item->parentItem : null)
                    ?? OrderItem::find($item->parent_item_id);

                if ($parent) {
                    $item->_combo_name = $parent->product_name;
                }
            }

            $printable->push($item);
        }

        return $printable->unique('id')->values();
    }

    private function buildHeader(): void
    {
        $s3 = chr(29) . chr(33) . chr(34);
        $s0 = chr(29) . chr(33) . chr(0);

        $this->blueprint[] = ['cmd' => 'align', 'val' => 'ct'];

        $headerText = 'CLIENTE';
        if ($this->order->type === 'table') {
            $headerText = 'MESA ' . ($this->order->table_number ?: '?');
        } elseif ($this->order->customer_id && $this->order->customer) {
            $headerText = $this->abbreviateName($this->order->customer->name);
        } elseif ($this->order->customer_name) {
            $headerText = $this->order->customer_name;
        } else {
            $headerText = $this->order->type === 'delivery' ? 'DELIVERY' : 'BALCAO';
        }

        if ($this->isReprint) {
            $this->blueprint[] = ['cmd' => 'text', 'val' => '*** REIMPRESSAO ***', 'bold' => true, 'size' => '1x'];
        }

        $this->blueprint[] = [
            'cmd'  => 'text',
            'val'  => $s3 . $this->cleanText($headerText) . $s0,
            'bold' => true
        ];

        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false, 'size' => '1x'];

        $time = $this->order->created_at ? $this->order->created_at->format('H:i') : now()->format('H:i');
        $orderIdFmt = number_format($this->order->id, 0, '', '.');
        $operator = $this->order->employee ? $this->abbreviateName($this->order->employee->name) : 'APP';
        $infoLine = "{$time} - #{$orderIdFmt} - {$operator}";

        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->cleanText($infoLine), 'bold' => false, 'size' => '1x'];

        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false, 'size' => '1x'];
        $this->blueprint[] = ['cmd' => 'align', 'val' => 'lt'];
    }

    private function buildItemBody($item, float $qty, bool $showQty, Collection $modifierGroups): void
    {
        $prefix = '';
        if ($showQty) {
            $qtyStr = $qty == floor($qty) ? (string)floor($qty) : number_format($qty, 1, ',', '');
            $prefix = $qtyStr . 'X ';
        }

        $productName = $item->product_name;
        if (isset($item->is_reward) && $item->is_reward) {
            $productName .= ' [CORTESIA]';
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => $this->cleanText($prefix . $productName), 'bold' => true, 'size' => '2x'];

        $sW = chr(29) . chr(33) . chr(16);
        $s0 = chr(29) . chr(33) . chr(0);

        $rawMods = $item->modifiers ?? $item->structured_modifiers ?? $item->structuredModifiers ?? [];
        $validModifiers = collect($rawMods)->map(function ($mod) {
            return is_array($mod) ? (object) $mod : $mod;
        })->filter(function ($mod) {
            return !str_contains(mb_strtoupper($mod->name ?? ''), 'VIAGEM');
        });

        if ($validModifiers->isNotEmpty()) {
            $itemQty = max(1, (float)($item->quantity ?: 1));

            $rankedModifiers = $validModifiers->map(function ($mod) use ($modifierGroups, $itemQty) {
                $groupId = $mod->modifier_group_id ?? $mod->group_id ?? null;
                $group = $groupId ? $modifierGroups->get($groupId) : null;
                $type = strtolower((string)($group?->type ?? $mod->type ?? $mod->group_type ?? ''));

                if (in_array($type, ['ingredient', 'ingrediente', 'flavor', 'sabor', 'option', 'opcao'])) {
                    $rank = 1;
                    $normalizedType = 'ingredient';
                } elseif (in_array($type, ['add_on', 'adicional', 'extra'])) {
                    $rank = 2;
                    $normalizedType = 'add_on';
                } elseif (in_array($type, ['observation', 'observacao', 'removal', 'remocao'])) {
                    $rank = 3;
                    $normalizedType = 'observation';
                } else {
                    $rank = ((float)($mod->price ?? 0) > 0) ? 2 : 1;
                    $normalizedType = ($rank === 2) ? 'add_on' : 'ingredient';
                }

                // 🚀 Cálculo da proporção de quantidade por unidade do item
                $modQty = (float)($mod->quantity ?? $mod->qty ?? 1);
                $unitQty = $itemQty > 0 ? ($modQty / $itemQty) : $modQty;

                // Ingredientes NUNCA recebem multiplicador numérico.
                // Adicionais só mostram o prefixo se a porção por unidade for maior que 1.
                $qtyPrefix = '';
                if ($normalizedType === 'add_on' && $unitQty > 1 && $modQty > $itemQty) {
                    $displayQty = (floor($unitQty) == $unitQty) ? (int)$unitQty : (int)$modQty;
                    $qtyPrefix = $displayQty . 'X ';
                }

                $mod->_hierarchy_rank = $rank;
                $mod->_normalized_type = $normalizedType;
                $mod->_qty_prefix = $qtyPrefix;
                return $mod;
            });

            $groupedByHierarchy = $rankedModifiers
                ->sortBy('_hierarchy_rank')
                ->groupBy('_hierarchy_rank');

            $firstGroup = true;
            foreach ($groupedByHierarchy as $rank => $mods) {
                if (!$firstGroup) {
                    $this->blueprint[] = ['cmd' => 'text', 'val' => ' ', 'size' => '1x'];
                }
                $firstGroup = false;

                foreach ($mods as $mod) {
                    $cleanName = trim(preg_replace('/^[\s•+\-]+/i', '', $mod->name ?? ''));
                    $cleanName = $this->cleanText($cleanName);

                    if (empty($cleanName)) continue;

                    $prefixMod = match ($mod->_normalized_type) {
                        'ingredient', 'observation' => '- ',
                        'add_on' => empty($mod->_qty_prefix) ? '+ ' : '  ' . $mod->_qty_prefix,
                    };

                    $this->blueprint[] = [
                        'cmd'  => 'text',
                        'val'  => $sW . $prefixMod . $cleanName . $s0,
                        'bold' => true
                    ];
                }
            }
        }

        $notesToPrint = [];
        if (!empty($item->_combo_name)) {
            $notesToPrint[] = $this->cleanText($item->_combo_name);
        }
        if (!empty($item->notes)) {
            $notesToPrint[] = $this->cleanText($item->notes);
        }

        if (!empty($notesToPrint)) {
            if ($validModifiers->isNotEmpty()) {
                $this->blueprint[] = ['cmd' => 'text', 'val' => ' ', 'size' => '1x'];
            }

            foreach ($notesToPrint as $note) {
                $this->blueprint[] = [
                    'cmd'  => 'text',
                    'val'  => $sW . 'Obs.: ' . $note . $s0,
                    'bold' => true
                ];
            }
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => ' ', 'size' => '1x'];

        $isTakeout = in_array($this->order->type, ['delivery', 'pickup', 'takeaway', 'counter']);
        if (!$isTakeout && isset($item->is_takeaway) && $item->is_takeaway) {
            $isTakeout = true;
        }

        if ($isTakeout) {
            $s3 = chr(29) . chr(33) . chr(34);

            $this->blueprint[] = ['cmd' => 'align', 'val' => 'ct'];
            $this->blueprint[] = [
                'cmd'  => 'text',
                'val'  => $s3 . '   VIAGEM   ' . $s0,
                'bold' => true,
                'invert' => true
            ];
            $this->blueprint[] = ['cmd' => 'align', 'val' => 'lt'];
        }

        $this->blueprint[] = ['cmd' => 'text', 'val' => '------------------------------------------------', 'bold' => false, 'size' => '1x'];
    }

    private function buildFooter(): void
    {
        $settings = CompanySetting::getCached();
        if ($settings && !empty($settings->receipt_footer_text)) {
            $this->blueprint[] = ['cmd' => 'align', 'val' => 'ct'];
            $this->blueprint[] = ['cmd' => 'text', 'val' => $this->cleanText($settings->receipt_footer_text), 'bold' => false, 'size' => '1x'];
            $this->blueprint[] = ['cmd' => 'align', 'val' => 'lt'];
        }
    }

    private function abbreviateName($name): string
    {
        if (!$name) return "";
        $cleanName = trim($name);
        if (is_numeric($cleanName)) return $cleanName;

        $parts = preg_split('/\s+/', $cleanName);
        if (count($parts) === 1) return strtoupper($parts[0]);

        return strtoupper($parts[0] . ' ' . substr(end($parts), 0, 1) . '.');
    }
}
