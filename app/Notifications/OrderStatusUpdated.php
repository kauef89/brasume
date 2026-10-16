<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use App\Models\Order;

class OrderStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public $order;
    public $message;

    public function __construct(Order $order, string $message)
    {
        $this->order = $order;
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        $statusMap = [
            'preparing' => '👨‍🍳 Na Cozinha',
            'ready' => '✅ Pronto!',
            'out_for_delivery' => '🛵 A Caminho',
            'dispatched' => '🛵 A Caminho',
            'closed' => '✨ Concluído'
        ];

        $title = $statusMap[$this->order->status] ?? 'Atualização do Pedido';

        $settings = \App\Models\CompanySetting::getCached();
        $badgeUrl = $settings && $settings->push_badge_path
            ? '/media/file?path=' . $settings->push_badge_path
            : '/icon-192x192.png';

        // 🚀 CORREÇÃO 3: Define um Topic (Vibration/Collapse ID).
        // Se o Firebase tiver 3 notificações pendentes para esse Pedido (Ex: Saiu, Chegou, Concluiu),
        // ele vai descartar as antigas e exibir apenas a última, preservando a bateria do cliente!
        return (new \NotificationChannels\WebPush\WebPushMessage)
            ->title($title)
            ->icon('/icon-192x192.png')
            ->badge($badgeUrl)
            ->body($this->message)
            ->data(['action_url' => '/customer/orders'])
            ->action('Acompanhar Pedido', '/customer/orders')
            ->options([
                'TTL' => 3600,
                'topic' => 'pedido_' . $this->order->id // 👈 A regra de Ouro!
            ]);
    }
}
