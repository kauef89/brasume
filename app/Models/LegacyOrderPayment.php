<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\LegacyOrder|null $legacyOrder
 * @property-read \App\Models\LegacyPaymentMethod|null $legacyPaymentMethod
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyOrderPayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyOrderPayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyOrderPayment query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class LegacyOrderPayment extends Model
{
    // Permitir preenchimento em massa
    protected $guarded = [];

    /**
     * O link de volta para o Pedido Legado.
     */
    public function legacyOrder(): BelongsTo
    {
        return $this->belongsTo(LegacyOrder::class, "legacy_order_id");
    }

    /**
     * O link para o método de pagamento no dicionário.
     */
    public function legacyPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(
            LegacyPaymentMethod::class,
            "legacy_payment_method_id",
        );
    }
}
