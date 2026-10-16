<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettingsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $tenantId) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("tenant.{$this->tenantId}.system"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'settings.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'tenantId'  => $this->tenantId,
            'timestamp' => now()->timestamp,
        ];
    }
}
