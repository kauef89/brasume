<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class SystemPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        // Resgata o ícone da empresa no cache
        $settings = \App\Models\CompanySetting::getCached();
        $badgeUrl = $settings && $settings->push_badge_path
            ? '/media/file?path=' . $settings->push_badge_path
            : '/icon-192x192.png';

        return (new WebPushMessage)
            ->title($this->title)
            ->icon('/icon-192x192.png')
            ->badge($badgeUrl)
            ->body($this->message)
            ->data(['action_url' => $this->actionUrl])
            ->action('Abrir Sistema', $this->actionUrl)
            ->options(['TTL' => 3600]);
    }
}
