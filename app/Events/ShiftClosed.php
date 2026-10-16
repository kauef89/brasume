<?php

namespace App\Events;

use App\Models\CashShift;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShiftClosed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CashShift $shift;
    public string $tenantId;

    public function __construct(CashShift $shift)
    {
        $this->shift = $shift;
        $this->tenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : 'central';
    }

    /**
     * Canais de transmissão no WebSocket isolados por tenant.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $prefix = $this->tenantId !== 'central' ? "tenant.{$this->tenantId}." : "";

        return [
            new Channel($prefix . 'shift'),
            new Channel($prefix . 'orders'),
        ];
    }

    /**
     * Nome do evento esperado pelo listener no frontend (.shift.closed).
     */
    public function broadcastAs(): string
    {
        return 'shift.closed';
    }

    /**
     * Dados limpos enviados para os navegadores e celulares.
     */
    public function broadcastWith(): array
    {
        return [
            'shift_id'  => $this->shift->id,
            'tenant_id' => $this->tenantId,
            'closed_at' => $this->shift->closed_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
