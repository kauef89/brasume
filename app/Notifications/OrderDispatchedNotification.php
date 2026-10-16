<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use App\Models\Order;

class OrderDispatchedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order
    ) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        $customerName = $this->order->customer_name ?? 'Cliente';

        return (new WebPushMessage)
            ->title('🛵 Novo Pedido para Entrega!')
            ->icon('/icon-192x192.png')
            ->body("Você tem um pedido de {$customerName} para entregar.")
            ->data(['action_url' => '/team?tab=driver'])
            ->action('Abrir Entregas', '/team?tab=driver')
            ->options(['TTL' => 3600]);
    }
}
