<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BridgeStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $tenantId;
    public string $status;
    public string $version;

    public function __construct(string $tenantId, string $status, string $version)
    {
        $this->tenantId = $tenantId;
        $this->status = $status; // 'online', 'outdated'
        $this->version = $version;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("tenant.{$this->tenantId}.system")
        ];
    }

    public function broadcastAs(): string
    {
        return 'bridge.status';
    }
}
