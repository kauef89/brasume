<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MagicLoginAuthorized implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $tenantId;
    public string $uuid;
    public string $token;

    public function __construct(string $tenantId, string $uuid, string $token)
    {
        $this->tenantId = $tenantId;
        $this->uuid = $uuid;
        $this->token = $token;
    }

    public function broadcastOn(): array
    {
        // 📡 O computador do caixa estará escutando este canal privado e único
        return [
            new Channel("tenant.{$this->tenantId}.auth.{$this->uuid}")
        ];
    }

    public function broadcastAs(): string
    {
        return 'magic.login.authorized';
    }
}
