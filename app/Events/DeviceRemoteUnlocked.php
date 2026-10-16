<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// 🚀 1. ShouldBroadcastNow: Ignora a fila e dispara o WebSocket instantaneamente!
class DeviceRemoteUnlocked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $deviceUuid;
    public string $token;
    public string $redirectUrl;

    public function __construct(string $deviceUuid, string $token, string $redirectUrl)
    {
        $this->deviceUuid = $deviceUuid;
        $this->token = $token;
        $this->redirectUrl = $redirectUrl;
    }

    public function broadcastOn(): array
    {
        // 🚀 2. Channel Público: O terminal consegue ouvir mesmo sem estar logado
        return [
            new Channel('device.' . $this->deviceUuid),
        ];
    }

    public function broadcastAs(): string
    {
        // 🚀 3. broadcastAs: Combina perfeitamente com o '.DeviceRemoteUnlocked' do Vue
        return 'DeviceRemoteUnlocked';
    }
}
