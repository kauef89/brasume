<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use App\Notifications\OrderDispatchedNotification;
use App\Services\RestaurantOps\ShiftAttendanceService;
use App\Events\OrderUpdated;
use Illuminate\Support\Facades\Notification;

class OrderStatusService
{
    public function updateStatus(Order $order, string $newStatus): void
    {
        $order->update(["status" => $newStatus]);

        broadcast(new OrderUpdated($order->fresh()));

        if ($newStatus === 'dispatched' || $newStatus === 'out_for_delivery') {
            if ($order->driver_id) {
                $driver = User::find($order->driver_id);
                if ($driver) {
                    $driver->notify(new OrderDispatchedNotification($order));
                }
            } else {
                $activeDrivers = ShiftAttendanceService::getActiveShiftStaffByRoles(['driver']);
                if ($activeDrivers->isNotEmpty()) {
                    Notification::send($activeDrivers, new OrderDispatchedNotification($order));
                }
            }
        }

        if ($order->customer_id) {
            $customer = Customer::with('user')->find($order->customer_id);

            if ($customer && $customer->user) {
                $pushMessage = $this->resolvePushMessage($order, $newStatus);
                if ($pushMessage) {
                    $customer->user->notify(new OrderStatusUpdated($order, $pushMessage));
                }
            }
        }
    }

    /**
     * Alterna a inibição preventiva de avaliação pelo caixa.
     */
    public function toggleRatingSuppression(Order $order, bool $skipRating): void
    {
        if ($skipRating) {
            $order->rating()->updateOrCreate(
                ['order_id' => $order->id],
                [
                    'customer_id' => $order->customer_id,
                    'rating'      => 0,
                    'comment'     => '[Sistema] Ocultado preventivamente pelo operador.',
                ]
            );
        } else {
            $order->rating()->where('rating', 0)->delete();
        }

        $order->touch();

        broadcast(new OrderUpdated($order->fresh(['rating', 'items', 'payments'])));
    }

    private function resolvePushMessage(Order $order, string $newStatus): ?string
    {
        if ($newStatus === 'preparing') return "👨‍🍳 O seu pedido #{$order->id} começou a ser preparado!";
        if ($newStatus === 'dispatched' && $order->type === 'delivery') return "🛵 O entregador está a caminho. Fique de olho!";
        if ($newStatus === 'closed') return "✨ Pedido concluído. Muito obrigado pela preferência!";

        if ($newStatus === 'ready') {
            if ($order->type === 'counter') {
                return "✅ Tudo pronto! Já pode vir retirar o seu pedido no balcão.";
            } elseif ($order->type === 'delivery' && $order->driver_id) {
                $driver = User::find($order->driver_id);
                if ($driver) {
                    $driver->notify(new OrderStatusUpdated($order, "🛵 O pedido de {$order->customer_name} está pronto no balcão para entregar!"));
                }
            }
        }

        return null;
    }
}
