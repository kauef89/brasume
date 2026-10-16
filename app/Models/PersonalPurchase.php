<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalPurchase extends Model
{
    protected $guarded = [];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Nota Fiscal de entrada vinculada a esta compra pessoal/despesa
     */
    public function receivedInvoice(): BelongsTo
    {
        return $this->belongsTo(ReceivedInvoice::class, 'received_invoice_id');
    }
}
