<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Services\RestaurantOps\KitchenDispatchService;
use App\Services\RestaurantOps\ShiftAttendanceService;
use App\Services\Marketing\ServerSideTrackingService;
use App\Notifications\NewB2COrderNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class CustomerOrderSideEffects
{
    public function __construct(
        protected KitchenDispatchService $kitchenDispatchService,
        protected ShiftAttendanceService $attendanceService,
        protected ServerSideTrackingService $trackingService
    ) {}

    /**
     * Orquestra todos os efeitos colaterais de uma encomenda de cliente.
     */
    public function handle(Order $order, bool $isAppend, bool $needsPixPayment): void
    {
        // 🍳 1. KDS & Produção física (somente se NÃO estiver retido aguardando Pix)
        if (!$needsPixPayment) {
            try {
                $this->kitchenDispatchService->dispatchNewItems($order);
            } catch (\Throwable $e) {
                Log::error("❌ [KDS/Print] Falha no despacho para produção do pedido #{$order->id}: " . $e->getMessage());
            }
        }

        // 📡 2. WebSockets em tempo real (atualiza POS, KDS e Kanban)
        try {
            if ($isAppend) {
                broadcast(new OrderUpdated($order));
            } else {
                broadcast(new OrderCreated($order));
            }
        } catch (\Throwable $e) {
            Log::error("🚨 [WebSockets] Falha ao transmitir broadcast do pedido B2C #{$order->id}: " . $e->getMessage());
        }

        // 🔔 3. Notificações da Equipe em Turno (Caixa, Bar, Cozinha)
        $this->notifyTeam($order, $needsPixPayment);

        // 📊 4. Marketing & Conversões (Server-Side Tracking)
        if (!$isAppend) {
            try {
                $this->trackingService->trackPurchase($order);
            } catch (\Throwable $e) {
                Log::warning("⚠️ [Tracking] Falha ao registrar tracking da compra #{$order->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Notifica os operadores em turno conforme a praça e o status do pagamento.
     */
    protected function notifyTeam(Order $order, bool $isPixWaiting): void
    {
        $order->loadMissing('items.product');

        // Notifica o Caixa / Frente de loja
        $cashierStaff = $this->attendanceService->getActiveShiftStaffByRoles(['caixa', 'pos', 'manager', 'admin', 'gerente']);
        if ($cashierStaff->isNotEmpty()) {
            $msgCaixa = $isPixWaiting
                ? "🔔 Novo Pedido #{$order->id} recebido (Aguardando Pix)! (Tipo: {$order->type})"
                : "🔔 Novo Pedido #{$order->id} na fila! (Tipo: {$order->type})";
            Notification::send($cashierStaff, new NewB2COrderNotification($order, $msgCaixa));
        }

        // Se estiver aguardando Pix, a cozinha e o bar não devem ser acionados ainda!
        if ($isPixWaiting) {
            return;
        }

        $activeStations = $order->items->pluck('product.production_station')->filter()->unique();

        foreach ($activeStations as $station) {
            $stationLower = mb_strtolower($station);

            if (str_contains($stationLower, 'bar') || $stationLower === 'bebidas') {
                $barStaff = $this->attendanceService->getActiveShiftStaffByRoles(['bar', 'bartender', 'copa']);
                if ($barStaff->isNotEmpty()) {
                    Notification::send($barStaff, new NewB2COrderNotification($order, "🍹 Novos itens de Bar solicitados no Pedido #{$order->id}!"));
                }
            }

            if (str_contains($stationLower, 'cozinha') || $stationLower === 'quente') {
                $kitchenStaff = $this->attendanceService->getActiveShiftStaffByRoles(['cozinha', 'cook', 'chef', 'kds']);
                if ($kitchenStaff->isNotEmpty()) {
                    Notification::send($kitchenStaff, new NewB2COrderNotification($order, "🍳 Novos pratos para preparo - Pedido #{$order->id}!"));
                }
            }
        }
    }
}
