<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PaymentMethod;

class LegacyPaymentMethod extends Model
{
    protected $guarded = [];

    // O link mágico para o presente
    public function currentPaymentMethod()
    {
        return $this->belongsTo(
            PaymentMethod::class,
            "current_payment_method_id",
        );
    }
}
