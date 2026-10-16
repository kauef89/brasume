<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderItemsSentToKitchen implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // 🚀 A MÁGICA: Só avisa a cozinha SE o banco de dados confirmar toda a transação financeira!
    public $afterCommit = true;

    public array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function broadcastOn(): array
    {
        $tenantId = tenant('id') ?? 'central';
        return [new Channel("tenant.{$tenantId}.kds")];
    }

    public function broadcastAs(): string
    {
        return 'items.created';
    }
}
