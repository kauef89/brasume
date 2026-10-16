<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceQrAuthorized implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $token,
        public int $userId
    ) {}

    public function broadcastOn(): array
    {
        // Canal público temporário e exclusivo para este token de QR Code
        return [
            new Channel('qr-auth.' . $this->token),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DeviceQrAuthorized';
    }
}
