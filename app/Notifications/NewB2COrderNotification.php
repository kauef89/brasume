<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use App\Models\Order;

class NewB2COrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $customMessage = '' // 🚀 Adicionamos o recebedor da mensagem customizada
    ) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        \Illuminate\Support\Facades\Log::info("📍 [SONDA PUSH BACK] Construindo payload WebPush para o usuário ID: {$notifiable->id}");

        $typeText = $this->order->type === 'delivery' ? 'entrega' : 'retirada';
        $customerName = $this->order->customer_name ?? 'Cliente';

        $body = $this->customMessage ?: "{$customerName} fez um pedido de {$typeText}.";

        return (new WebPushMessage)
            ->title('🛒 Novo Pedido On-line!')
            ->icon('/icon-192x192.png')
            ->body($body)
            ->data(['action_url' => "/team/mobile/operations"])
            ->action('Ver Pedido', "/team/mobile/operations")
            ->options(['TTL' => 3600]);
    }
}
