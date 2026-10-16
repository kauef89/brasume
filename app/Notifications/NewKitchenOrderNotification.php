<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class NewKitchenOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int|string $orderId
    ) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('👨‍🍳 Novo Pedido na Cozinha!')
            ->icon('/icon-192x192.png')
            ->body("Sua cozinha tem um novo pedido (#{$this->orderId}).")
            ->data(['action_url' => '/kds'])
            ->action('Abrir KDS', '/kds')
            ->options(['TTL' => 1800]);
    }
}
