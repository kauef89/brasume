<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

// 🚀 USAMOS O "ShouldBroadcastNow" PARA ZERO LATÊNCIA!
// Se usássemos apenas "ShouldBroadcast", o trabalho iria para a fila (Queue)
// e poderia atrasar 2 ou 3 segundos. Na cozinha, queremos impacto imediato!
class PrintJobCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $tenantId;
    public array $payload;

    /**
     * Create a new event instance.
     */
    public function __construct(string $tenantId, array $payload)
    {
        $this->tenantId = $tenantId;
        $this->payload = $payload;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // 📡 Criamos um canal dedicado apenas a impressoras para não poluir o canal de Pedidos
        return [
            new Channel('tenant.' . $this->tenantId . '.printers'),
        ];
    }

    /**
     * Opcional: Personalizar o nome do evento que o Javascript vai escutar
     */
    public function broadcastAs(): string
    {
        return 'print.job.created';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Enviamos todo aquele "JSON Blueprint" formatado no Service
        return $this->payload;
    }
}
