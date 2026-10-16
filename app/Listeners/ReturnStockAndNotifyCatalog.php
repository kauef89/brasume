<?php

namespace App\Listeners;

use App\Events\CatalogUpdated;
use App\Events\OrderCancelled;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ReturnStockAndNotifyCatalog implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Notifica as aplicações que o estoque/cardápio sofreu alteração por cancelamento.
     */
    public function handle(OrderCancelled $event): void
    {
        try {
            if ($event->tenantId !== 'central') {
                $tenant = Tenant::find($event->tenantId);
                if ($tenant) {
                    tenancy()->initialize($tenant);
                }
            }

            // O estorno de estoque já foi processado pelo OrderCancellationService de forma atômica
            broadcast(new CatalogUpdated($event->tenantId));
            Log::info("🔄 [ReturnStockAndNotifyCatalog] Notificação de catálogo atualizada para Pedido #{$event->order->id}.");

            if ($event->tenantId !== 'central') {
                tenancy()->end();
            }

        } catch (\Throwable $e) {
            Log::error("❌ [ReturnStockAndNotifyCatalog] Falha ao notificar atualização de catálogo: " . $e->getMessage());
        }
    }
}
