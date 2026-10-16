<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Order|null $order
 * @property-read \App\Models\Printer|null $printer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrintJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrintJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrintJob query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class PrintJob extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array', // 🚀 Converte o JSON do banco para Array no PHP automaticamente!
    ];

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
