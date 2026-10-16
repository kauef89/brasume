<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Models\Tenant;
use App\Services\Financial\PaymentGatewayManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessGatewayRefund implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(protected PaymentGatewayManager $gatewayManager) {}

    public function handle(OrderCancelled $event): void
    {
        try {
            if ($event->tenantId !== 'central') {
                $tenant = Tenant::find($event->tenantId);
                if ($tenant) tenancy()->initialize($tenant);
            }

            $order = $event->order;

            // Só processa se o pedido realmente tiver um TXID (foi gerado PIX Dinâmico)
            // e se tiver pagamentos registrados com sucesso
            $totalPaid = $order->payments()->sum('amount');

            if ($order->txid && $totalPaid > 0) {
                // Recupera a integração ativa. No futuro, isso pode vir de uma configuração do banco.
                $gateway = $this->gatewayManager->resolveGateway('sicredi_pix');

                Log::info("🔄 [ProcessGatewayRefund] Iniciando estorno automático de R$ {$totalPaid} para o Pedido #{$order->id}...");

                $gateway->refundPayment($order, $totalPaid);

                Log::info("✅ [ProcessGatewayRefund] Estorno concluído com sucesso para o Pedido #{$order->id}.");
            }

            if ($event->tenantId !== 'central') tenancy()->end();

        } catch (\Exception $e) {
            Log::error("❌ [ProcessGatewayRefund] Falha ao processar estorno do Pedido #{$event->order->id}: " . $e->getMessage());
        }
    }
}
