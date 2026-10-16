<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast; // 🚀 VAI PARA A FILA!
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceUnlockRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $tenantId;
    public string $deviceUuid;
    public string $deviceName;

    public function __construct(string $tenantId, string $deviceUuid, string $deviceName)
    {
        $this->tenantId = $tenantId;
        $this->deviceUuid = $deviceUuid;
        $this->deviceName = $deviceName;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("tenant.{$this->tenantId}.fleet"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DeviceUnlockRequested';
    }
}
