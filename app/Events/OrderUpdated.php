<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $tenantId;
    public bool $isPixWebhook;

    public function __construct(public Order $order, bool $isPixWebhook = false)
    {
        $this->tenantId = (string) (tenant('id') ?? 'central');
        $this->isPixWebhook = $isPixWebhook;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new Channel("tenant.{$this->tenantId}.operations"),
        ];

        if ($this->order->customer_id) {
            $channels[] = new Channel("tenant.{$this->tenantId}.customer.{$this->order->customer_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id'             => $this->order->id,
            'orderId'        => $this->order->id,
            'customer_id'    => $this->order->customer_id,
            'status'         => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'is_pix_webhook' => $this->isPixWebhook,
            'tenantId'       => $this->tenantId,
        ];
    }
}
