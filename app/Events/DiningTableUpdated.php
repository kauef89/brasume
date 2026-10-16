<?php

namespace App\Events;

use App\Models\DiningTable;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiningTableUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $tenantId;

    public function __construct(public DiningTable $table)
    {
        $this->tenantId = (string) (tenant('id') ?? 'central');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("tenant.{$this->tenantId}.operations"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'table.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id'       => $this->table->id,
            'tableId'  => $this->table->id,
            'status'   => $this->table->status,
            'tenantId' => $this->tenantId,
        ];
    }
}
