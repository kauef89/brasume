<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockLowDetected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Product $product;
    public string $urgency;
    public string $tenantId;

    public function __construct(Product $product, string $urgency = 'normal')
    {
        $this->product = $product;
        $this->urgency = $urgency;
        $this->tenantId = tenant('id') ?? 'central';
    }
}
