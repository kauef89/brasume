<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalPurchase extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'auto_approve' => 'boolean',
            'quantity'     => 'decimal:4',
            'unit_cost'    => 'decimal:5',
            'total_cost'   => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedInvoice(): BelongsTo
    {
        return $this->belongsTo(ReceivedInvoice::class, 'received_invoice_id');
    }
}
