<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CatalogUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $afterCommit = true;

    public string $tenantId;

    public function __construct(?string $tenantId = null)
    {
        // 🚀 Auto-detecta o tenant atual ('nativa') com fallback seguro
        $this->tenantId = $tenantId ?? (string) (tenant('id') ?? 'central');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("tenant.{$this->tenantId}.catalog")
        ];
    }

    public function broadcastAs(): string
    {
        return 'catalog.updated';
    }
}
