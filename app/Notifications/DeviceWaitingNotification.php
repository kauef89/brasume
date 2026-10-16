<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // 🚀 OBRIGATÓRIO PARA A FILA
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class DeviceWaitingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $deviceName;
    public string $deviceType;

    public function __construct(string $deviceName, string $deviceType)
    {
        $this->deviceName = $deviceName;
        $this->deviceType = $deviceType;
    }

    public function via($notifiable): array
    {
        // 🚀 Define os canais (Database para o sininho no painel, WebPush para o telemóvel)
        return ['database', WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification)
    {
        $tipo = $this->deviceType === 'kds' ? 'Cozinha (KDS)' : 'Caixa / POS';

        return (new WebPushMessage)
            ->title('Acesso Solicitado')
            ->icon('/icon-192x192.png')
            ->body("O terminal {$this->deviceName} ({$tipo}) está a pedir liberação de acesso.")
            ->action('Autorizar Agora', '/team/mobile/menu');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Acesso Solicitado',
            'message' => "O terminal {$this->deviceName} requer a sua autorização.",
            'device' => $this->deviceName
        ];
    }
}
