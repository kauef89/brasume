<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class StoreOpenedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('🍔 Estamos Abertos!')
            ->icon('/icon-192x192.png')
            ->body('A espera acabou! A loja acabou de abrir. Volte para finalizar o seu pedido.')
            ->data(['action_url' => "/customer/cart"])
            ->action('Finalizar Pedido', "/customer/cart")
            ->options(['TTL' => 3600]);
    }
}
