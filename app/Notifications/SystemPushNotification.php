<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;
use App\Notifications\Concerns\HasTenantWebPush;

class SystemPushNotification extends Notification implements ShouldQueue
{
    use Queueable, HasTenantWebPush;

    public string $title;
    public string $message;
    public string $actionUrl;

    public function __construct(string $title, string $message, string $actionUrl = '/')
    {
        $this->title = $title;
        $this->message = $message;
        $this->actionUrl = $actionUrl;
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $this->applyTenantVapidSubject();

        $iconUrl = $this->resolveTenantIconUrl();
        $badgeUrl = $this->resolveTenantBadgeUrl();
        $fullActionUrl = str_starts_with($this->actionUrl, 'http')
            ? $this->actionUrl
            : $this->resolveTenantActionUrl($this->actionUrl);

        return (new WebPushMessage)
            ->title($this->title)
            ->icon($iconUrl)
            ->badge($badgeUrl)
            ->body($this->message)
            ->tag('system_notice')
            ->data([
                'action_url' => $fullActionUrl,
                'tag'        => 'system_notice'
            ])
            ->action('Abrir Sistema', $fullActionUrl)
            ->options(['TTL' => 3600]);
    }
}