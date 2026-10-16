<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $tenantId;
    public array $orderData;
    protected ?int $customerId;

    public function __construct(public Order $order)
    {
        $this->tenantId = (string) (tenant('id') ?? 'central');
        $this->customerId = $order->customer_id ? (int) $order->customer_id : null;

        $this->orderData = [
            'id'              => $order->id,
            'customer_id'     => $this->customerId,
            'status'          => 'cancelled',
            'payment_status'  => $order->payment_status,
            'type'            => $order->type,
            'dining_table_id' => $order->dining_table_id,
            'table_number'    => $order->table_number,
            'total'           => (float) $order->total,
            'cancelled_at'    => now()->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        $channels = [
            new Channel("tenant.{$this->tenantId}.operations"),
            new Channel("tenant.{$this->tenantId}.orders"),
        ];

        if ($this->customerId) {
            $channels[] = new Channel("tenant.{$this->tenantId}.customer.{$this->customerId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.cancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'orderId'   => $this->order->id,
            'orderData' => $this->orderData,
            'tenantId'  => $this->tenantId,
        ];
    }
}
