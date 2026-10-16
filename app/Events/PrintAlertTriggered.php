<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintAlertTriggered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;
    public string $tenantId;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->tenantId = (string) (tenant('id') ?? 'central');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("tenant.{$this->tenantId}.operations"),
            new Channel("tenant.{$this->tenantId}.pos"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'print.alert';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
