<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TerminalUnlocked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $deviceUuid, // 🚀 Substituímos de "Token" efêmero para "DeviceUUID" permanente
        public string $redirectUrl,
        public int $userId
    ) {}

    public function broadcastOn(): array
    {
        // 🚀 Agora todos os terminais do salão usarão a mesma regra para escutar os gestores!
        return [
            new Channel("device.{$this->deviceUuid}")
        ];
    }

    public function broadcastAs(): string
    {
        return 'terminal.unlocked';
    }
}
