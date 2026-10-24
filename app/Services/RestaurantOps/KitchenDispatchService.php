<?php

namespace App\Services\RestaurantOps;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Kitchen;
use App\Models\Printer;
use App\Models\CompanySetting;
use App\Services\Printing\PrintDispatcher;
use App\Services\Printing\Documents\KitchenTicket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class KitchenDispatchService
{
    /**
     * Despacha novos itens para produção (KDS e Impressoras Físicas).
     */
    public function dispatchNewItems(Order $order, string $printMode = 'station', bool $separateMultiples = false): void
    {
        // Busca apenas os itens "reais" (filhos do combo ou itens soltos), descartando os cabeçalhos pais
        $parentIds = OrderItem::where('order_id', $order->id)
            ->whereNotNull('parent_item_id')
            ->pluck('parent_item_id')
            ->unique()
            ->toArray();

        $newItems = OrderItem::with(['modifiers', 'product.category', 'kitchen.printer', 'product.kitchen'])
            ->where('order_id', $order->id)
            ->where('status', 'active')
            ->where('production_status', 'pending')
            ->whereNull('fired_at')
            ->whereNotIn('id', $parentIds)
            ->get();

        if ($newItems->isEmpty()) {
            return;
        }

        $now = now();
        $itemIds = $newItems->pluck('id')->all();

        OrderItem::whereIn('id', $itemIds)->update([
            'fired_at' => $now,
        ]);

        // Disparo único de impressão física (síncrono e confiável)
        try {
            $this->printPhysicalTickets($order, $newItems, $printMode, $separateMultiples);
        } catch (\Exception $e) {
            Log::error("❌ Erro ao disparar impressão de cozinha: " . $e->getMessage());
        }
    }

    protected function printPhysicalTickets(Order $order, Collection $items, string $printMode, bool $separateMultiples): void
    {
        $dispatcher = app(PrintDispatcher::class);
        $settings = CompanySetting::getCached();

        // 🍸 REGRA DE NEGÓCIO: Suprime impressão de itens de bar/copa quando o pedido for de mesa
        if ($order->type === 'table' && (bool) ($settings?->suppress_bar_print_tables)) {
            $items = $items->reject(function ($item) {
                $kitchen = $item->kitchen ?? $item->product?->kitchen;

                if (!$kitchen && $item->kitchen_id) {
                    $kitchen = Kitchen::find($item->kitchen_id);
                }

                if ($kitchen) {
                    $type = strtolower((string) ($kitchen->type ?? ''));
                    $name = strtolower((string) ($kitchen->name ?? ''));

                    if ($type === 'bar' || str_contains($name, 'bar') || str_contains($name, 'copa')) {
                        return true;
                    }
                }

                if ($item->product?->production_station) {
                    $station = strtolower($item->product->production_station);
                    if (str_contains($station, 'bar') || str_contains($station, 'copa') || $station === 'bebidas') {
                        return true;
                    }
                }

                return false;
            });
        }

        if ($items->isEmpty()) {
            return;
        }

        // 📋 ORDENAÇÃO INTELIGENTE DE PRODUÇÃO
        // Agrupa por Categoria -> Nome do Produto -> ID do Item
        $items = $this->sortItemsForKitchenProduction($items);

        $actualPrintMode = $settings?->kitchen_print_mode ?? $printMode;
        $actualSeparateMultiples = (bool) ($settings?->kitchen_separate_multiples ?? $separateMultiples);

        $cashierPrinter = Printer::where('is_cashier', true)->where('is_active', true)->first()
            ?? Printer::where('is_active', true)->first();

        // 🚀 MODO UNIFICADO
        if ($actualPrintMode === 'unified') {
            $printer = Printer::where('is_active', true)->where('is_cashier', false)->first()
                    ?? $cashierPrinter;

            if ($printer) {
                $document = new KitchenTicket($order, $items, $actualPrintMode, $actualSeparateMultiples, false);
                $dispatcher->dispatch($printer, $document);
            }
            return;
        }

        // 🚀 AGRUPAMENTO POR IMPRESSORA DE DESTINO
        $groupedByPrinter = [];

        foreach ($items as $item) {
            $targetPrinter = null;

            if ($item->kitchen?->printer && $item->kitchen->printer->is_active) {
                $targetPrinter = $item->kitchen->printer;
            } elseif ($item->kitchen_id) {
                $kitchen = Kitchen::with('printer')->find($item->kitchen_id);
                if ($kitchen?->printer && $kitchen->printer->is_active) {
                    $targetPrinter = $kitchen->printer;
                }
            }

            $targetPrinter = $targetPrinter ?? $cashierPrinter;

            if ($targetPrinter && $targetPrinter->is_active) {
                $printerId = $targetPrinter->id;
                if (!isset($groupedByPrinter[$printerId])) {
                    $groupedByPrinter[$printerId] = [
                        'printer' => $targetPrinter,
                        'items'   => collect(),
                    ];
                }
                $groupedByPrinter[$printerId]['items']->push($item);
            }
        }

        foreach ($groupedByPrinter as $group) {
            $printer = $group['printer'];
            $printerItems = $group['items'];

            if ($actualPrintMode === 'item') {
                foreach ($printerItems as $item) {
                    $copies = $actualSeparateMultiples ? (int) $item->quantity : 1;
                    $printItem = clone $item;

                    if ($actualSeparateMultiples) {
                        $printItem->quantity = 1;
                    }

                    for ($i = 0; $i < $copies; $i++) {
                        $document = new KitchenTicket($order, collect([$printItem]), 'item', $actualSeparateMultiples, false);
                        $dispatcher->dispatch($printer, $document);
                    }
                }
            } else {
                $document = new KitchenTicket($order, $printerItems, $actualPrintMode, $actualSeparateMultiples, false);
                $dispatcher->dispatch($printer, $document);
            }
        }
    }

    /**
     * Ordena itens por categoria e produto para facilitar a esteira de produção.
     */
    protected function sortItemsForKitchenProduction(Collection $items): Collection
    {
        return $items->sort(function ($a, $b) {
            // 1. Prioridade: Cozinha / Praça de Produção
            $kitchenA = $a->kitchen ?? $a->product?->kitchen;
            $kitchenB = $b->kitchen ?? $b->product?->kitchen;

            $kitchenNameA = strtolower((string) ($kitchenA?->name ?? 'geral'));
            $kitchenNameB = strtolower((string) ($kitchenB?->name ?? 'geral'));

            if ($kitchenNameA !== $kitchenNameB) {
                return strcmp($kitchenNameA, $kitchenNameB);
            }

            // 2. Categoria do Produto
            $catA = strtolower((string) ($a->product?->category?->name ?? ''));
            $catB = strtolower((string) ($b->product?->category?->name ?? ''));

            if ($catA !== $catB) {
                return strcmp($catA, $catB);
            }

            // 3. Nome do Produto
            $prodNameA = strtolower((string) ($a->product_name ?: ($a->product?->name ?? '')));
            $prodNameB = strtolower((string) ($b->product_name ?: ($b->product?->name ?? '')));

            if ($prodNameA !== $prodNameB) {
                return strcmp($prodNameA, $prodNameB);
            }

            // 4. ID do Item (ordem de inserção)
            return $a->id <=> $b->id;
        })->values();
    }
}
