<?php

namespace App\Listeners;

use App\Events\OrderItemsSentToKitchen;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Kitchen;
use App\Models\Tenant;
use App\Models\CompanySetting;
use App\Services\Printing\PrintDispatcher;
use App\Services\Printing\Documents\KitchenTicket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendToKitchenPrinters implements ShouldQueue
{
    use InteractsWithQueue;

    public $afterCommit = true;

    public function __construct(private PrintDispatcher $printDispatcher) {}

    public function handle(OrderItemsSentToKitchen $event): void
    {
        try {
            $itemsData = $event->items;
            if (empty($itemsData)) return;

            $tenantId = $itemsData[0]['tenant_id'] ?? null;
            if ($tenantId && $tenantId !== 'central') {
                $tenant = Tenant::find($tenantId);
                if ($tenant) tenancy()->initialize($tenant);
            }

            $orderId = $itemsData[0]['order']['id'] ?? $itemsData[0]['order_id'];
            $order = Order::find($orderId);
            if (!$order) return;

            $itemIds = array_column($itemsData, 'id');
            $orderItems = OrderItem::with(['modifiers', 'subItems.modifiers', 'product.category'])
                ->whereIn('id', $itemIds)
                ->get();

            if ($orderItems->isEmpty()) {
                if ($tenantId && $tenantId !== 'central') tenancy()->end();
                return;
            }

            $settings = CompanySetting::first();

            $printMode = $settings->kitchen_print_mode ?? 'station';
            $separateMultiples = $settings->kitchen_separate_multiples ?? false;
            $cashierPrinter = \App\Models\Printer::where('is_cashier', true)->where('is_active', true)->first();

            // 🚀 Identifica se há itens pai de combo presentes no lote
            $parentIds = $orderItems->pluck('parent_item_id')->filter()->unique()->toArray();

            $groupedByPrinter = [];

            foreach ($orderItems as $item) {
                // Se o item é apenas o cabeçalho do combo e os filhos estão no pedido, não despacha o pai para produção
                if (in_array($item->id, $parentIds)) {
                    continue;
                }

                $targetPrinter = null;

                if ($printMode !== 'order' && $item->kitchen_id) {
                    $kit = Kitchen::with('printer')->find($item->kitchen_id);
                    if ($kit && $kit->printer && $kit->printer->is_active) {
                        $targetPrinter = $kit->printer;
                    }
                }

                $targetPrinter = $targetPrinter ?? $cashierPrinter;

                if ($targetPrinter && $targetPrinter->is_active) {
                    $printerId = $targetPrinter->id;
                    if (!isset($groupedByPrinter[$printerId])) {
                        $groupedByPrinter[$printerId] = [
                            'printer' => $targetPrinter,
                            'items' => collect()
                        ];
                    }
                    $groupedByPrinter[$printerId]['items']->push($item);
                }
            }

            foreach ($groupedByPrinter as $group) {
                $doc = new KitchenTicket($order, $group['items'], $printMode, $separateMultiples);
                $this->printDispatcher->dispatch($group['printer'], $doc);
            }

            if ($tenantId && $tenantId !== 'central') tenancy()->end();

        } catch (\Exception $e) {
            Log::error("ERRO FATAL NA FILA DE COZINHA (Listener): " . $e->getMessage());
        }
    }
}
