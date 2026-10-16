<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceRemoteLocked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $deviceUuid;

    public function __construct(string $deviceUuid)
    {
        $this->deviceUuid = $deviceUuid;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('device.' . $this->deviceUuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DeviceRemoteLocked';
    }
}
