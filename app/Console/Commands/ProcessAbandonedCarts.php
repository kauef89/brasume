<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\Order;
use App\Services\Orders\OrderCancellationService;
use Illuminate\Support\Facades\Log;

class ProcessAbandonedCarts extends Command
{
    protected $signature = 'erp:process-abandoned-carts
                            {--tenant= : ID do Tenant específico}
                            {--on-shift-close : Executa varredura exclusiva no encerramento de turno}';

    protected $description = 'Cancela comandas e rascunhos que ficaram pendentes após o encerramento do turno.';

    public function handle(OrderCancellationService $cancellationService): int
    {
        // 🛡️ TRAVA ABSOLUTA: Proíbe execução automática fora do fechamento de turno
        if (! $this->option('on-shift-close')) {
            $this->error('⛔ OPERAÇÃO BLOQUEADA: Nenhuma comanda pode ser cancelada durante o turno.');
            $this->line('Esta rotina só pode ser executada no encerramento de turno com a flag [--on-shift-close].');
            Log::warning('⚠️ [ProcessAbandonedCarts] Tentativa de execução abortada: flag --on-shift-close ausente.');
            return self::FAILURE;
        }

        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            $this->purgePendingOrdersAfterShift($tenant, $cancellationService);

            tenancy()->end();
        }

        return self::SUCCESS;
    }

    protected function purgePendingOrdersAfterShift(Tenant $tenant, OrderCancellationService $cancellationService): void
    {
        // 🧹 No fechamento do turno, busca TUDO que ficou pendente e não foi pago
        $abandonedOrders = Order::with('items')
            ->where('status', 'pending')
            ->where('payment_status', 'pending')
            ->whereDoesntHave('payments', function ($q) {
                $q->where('method', 'not like', '%Cashback%');
            })
            ->get();

        if ($abandonedOrders->isEmpty()) {
            return;
        }

        Log::info("🧹 [ShiftClose-Cleanup] Tenant [{$tenant->id}]: Encontrados {$abandonedOrders->count()} pedido(s) pendente(s) para purga de final de turno.");

        $cancelReason = 'Cancelamento de Fim de Turno: Comanda não finalizada';

        foreach ($abandonedOrders as $order) {
            try {
                $cancellationService->cancelBySystem($order, $cancelReason);
                Log::info("✅ [ShiftClose-Cleanup] Pedido #{$order->id} (Origem: {$order->source}, Tipo: {$order->type}) cancelado e estoque estornado.");
            } catch (\Throwable $e) {
                Log::error("❌ [ShiftClose-Cleanup] Falha ao cancelar Pedido #{$order->id}: " . $e->getMessage());
            }
        }
    }
}
