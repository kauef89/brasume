<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Notifications\NewB2COrderNotification;
use App\Notifications\OrderStatusUpdated;
use App\Services\RestaurantOps\ShiftAttendanceService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class OrderNotificationDispatcher
{
    public function __construct(
        protected ShiftAttendanceService $attendanceService
    ) {}

    /**
     * Notifica a equipe de atendimento e produção sobre um novo pedido B2C.
     */
    public function notifyTeamOfNewOrder(Order $order): void
    {
        $order->loadMissing('items.product');

        $cashierStaff = $this->attendanceService->getActiveShiftStaffByRoles(['caixa', 'pos', 'manager', 'admin', 'gerente']);

        if ($cashierStaff->isNotEmpty()) {
            $msgCaixa = "🔔 Novo Pedido #{$order->id} na fila! (Tipo: {$order->type})";
            Notification::send($cashierStaff, new NewB2COrderNotification($order, $msgCaixa));
        }

        $activeStations = $order->items->pluck('product.production_station')->filter()->unique();

        foreach ($activeStations as $station) {
            $stationLower = strtolower($station);

            if (str_contains($stationLower, 'bar') || $stationLower === 'bebidas') {
                $barStaff = $this->attendanceService->getActiveShiftStaffByRoles(['bar', 'bartender', 'copa']);
                if ($barStaff->isNotEmpty()) {
                    $msgBar = "🍹 Novos itens de Bar solicitados no Pedido #{$order->id}!";
                    Notification::send($barStaff, new NewB2COrderNotification($order, $msgBar));
                }
            }

            if (str_contains($stationLower, 'cozinha') || $stationLower === 'quente') {
                $kitchenStaff = $this->attendanceService->getActiveShiftStaffByRoles(['cozinha', 'cook', 'chef', 'kds']);
                if ($kitchenStaff->isNotEmpty()) {
                    $msgCozinha = "🍳 Novos pratos para preparo - Pedido #{$order->id}!";
                    Notification::send($kitchenStaff, new NewB2COrderNotification($order, $msgCozinha));
                }
            }
        }
    }

    /**
     * Notifica a equipe operacional sobre o cancelamento de um pedido.
     */
    public function notifyTeamOfCancellation(Order $order, string $message): void
    {
        $posStaff = $this->attendanceService->getActiveShiftStaffByRoles(['pos', 'manager', 'admin', 'caixa']);

        if ($posStaff->isNotEmpty()) {
            Notification::send($posStaff, new OrderStatusUpdated($order, $message));
        }
    }
}
