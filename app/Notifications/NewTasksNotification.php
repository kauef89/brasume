<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
// 🚀 Adicionada a interface ShouldQueue:
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class NewTasksNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $message;

    public function __construct(string $message = 'Novas rotinas foram liberadas para o seu setor.')
    {
        $this->message = $message;
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('Novas Tarefas! 📋')
            ->icon('/images/icon.png')
            ->body($this->message)
            ->action('Abrir Tarefas', 'view_tasks')
            ->options(['TTL' => 1000]);
    }
}
