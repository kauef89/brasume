<?php

namespace App\Events;

use App\Models\CashShift;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShiftOpened implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CashShift $shift;
    public string $tenantId;

    public function __construct(CashShift $shift)
    {
        $this->shift = $shift;
        $this->tenantId = (string) (tenant('id') ?? 'central');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("tenant.{$this->tenantId}.operations"),
            new Channel("tenant.{$this->tenantId}.shift"),
            new Channel("tenant.{$this->tenantId}.orders"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'shift.opened';
    }

    public function broadcastWith(): array
    {
        return [
            'shift_id'   => $this->shift->id,
            'tenant_id'  => $this->tenantId,
            'opened_at'  => $this->shift->opened_at?->toIso8601String() ?? now()->toIso8601String(),
            'is_open'    => true,
        ];
    }
}
