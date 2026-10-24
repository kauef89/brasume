<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use App\Models\Order;
use App\Notifications\Concerns\HasTenantWebPush;

class OrderStatusUpdated extends Notification implements ShouldQueue
{
    use Queueable, HasTenantWebPush;

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
        $this->applyTenantVapidSubject();

        $statusMap = [
            'preparing'        => '👨‍🍳 Na Cozinha',
            'ready'            => '✅ Pronto!',
            'out_for_delivery' => '🛵 A Caminho',
            'dispatched'       => '🛵 A Caminho',
            'closed'           => '✨ Concluído'
        ];

        $title = $statusMap[$this->order->status] ?? 'Atualização do Pedido';
        $iconUrl = $this->resolveTenantIconUrl();
        $badgeUrl = $this->resolveTenantBadgeUrl();
        $actionUrl = $this->resolveTenantActionUrl('/customer/orders');
        $orderTag = 'order_' . $this->order->id;

        return (new WebPushMessage)
            ->title($title)
            ->icon($iconUrl)
            ->badge($badgeUrl)
            ->body($this->message)
            ->tag($orderTag)
            ->data([
                'action_url' => $actionUrl,
                'tag'        => $orderTag
            ])
            ->action('Acompanhar Pedido', $actionUrl)
            ->options([
                'TTL'   => 3600,
                'topic' => $orderTag
            ]);
    }
}