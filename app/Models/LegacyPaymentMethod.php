<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PaymentMethod;

/**
 * @property-read PaymentMethod|null $currentPaymentMethod
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyPaymentMethod newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyPaymentMethod newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyPaymentMethod query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
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
