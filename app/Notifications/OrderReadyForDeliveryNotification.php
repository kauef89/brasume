<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class OrderReadyForDeliveryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $orderId = $this->order->id;
        $neighborhood = $this->order->neighborhood
            ?? $this->order->address_snapshot['neighborhood']
            ?? $this->order->address?->neighborhood
            ?? 'Delivery';

        $totalFormatted = number_format((float) ($this->order->total ?? $this->order->balance_due ?? 0), 2, ',', '.');
        $isPaid = $this->order->payment_status === 'paid';
        $paymentText = $isPaid ? 'Já Pago' : "Cobrar R$ {$totalFormatted}";

        return (new WebPushMessage)
            ->title("🛵 Pedido #{$orderId} Pronto para Entrega!")
            ->icon('/icons/icon-192x192.png')
            ->badge('/icons/icon-192x192.png')
            ->body("Bairro {$neighborhood} • {$paymentText}. Já embalado no balcão.")
            ->tag("order-ready-{$orderId}")
            ->data([
                'url' => url('/team/driver'),
                'order_id' => $orderId
            ])
            ->action('Abrir Pedido', 'open_order');
    }
}
