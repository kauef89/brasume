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

        // 🚀 AVALIAÇÃO FISCAL NO FECHAMENTO
        if (in_array($newStatus, ['closed', 'completed'])) {
            $this->evaluateFiscalEmission($order);
        }

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
     * Avalia as formas de pagamento do pedido e define se emite NFC-e ou marca como dispensada.
     */
    private function evaluateFiscalEmission(Order $order): void
    {
        // Se a nota já foi autorizada previamente, preserva
        if (in_array($order->fiscal_status, ['authorized', 'emitted'])) {
            return;
        }

        $order->loadMissing('payments.paymentMethod');

        // Se qualquer forma de pagamento usada tiver auto_fiscal_emission = false, dispensa a emissão
        $shouldSkip = $order->payments->contains(function ($payment) {
            return $payment->paymentMethod && !$payment->paymentMethod->auto_fiscal_emission;
        });

        if ($shouldSkip || $order->payments->isEmpty()) {
            $order->update([
                'fiscal_status' => 'skipped',
                'fiscal_error'  => null,
            ]);
            return;
        }

        // Se todas as formas permitem emissão automática e o certificado estivesse ativo:
        // Caso seu disparo já seja feito por um Job automático, marque como 'pending'
        if (empty($order->fiscal_status) || $order->fiscal_status === 'skipped') {
            $order->update(['fiscal_status' => 'pending']);

            // Se você despacha o Job de emissão por aqui:
            // dispatch(new \App\Jobs\EmitNfceJob($order->id, tenant('id')));
        }
    }

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
        if ($newStatus === 'delivered' && $order->type === 'delivery') return '🎉 Pedido entregue! Bom apetite!';
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
