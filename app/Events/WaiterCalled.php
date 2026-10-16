<?php

namespace App\Events;

use App\Models\DiningTable;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WaiterCalled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DiningTable $table,
        public string $customerName,
        public string $tenantId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("tenant.{$this->tenantId}.operations")
        ];
    }

    public function broadcastAs(): string
    {
        return 'waiter.called';
    }

    public function broadcastWith(): array
    {
        return [
            'table_id'      => $this->table->id,
            'table_name'    => $this->table->name,
            'customer_name' => $this->customerName,
            'called_at'     => now()->toIso8601String(),
        ];
    }
}
