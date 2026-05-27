<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @mixin Builder
 */
class FiscalDocument extends Model
{
    protected $guarded = [];

    protected $casts = [
        'issue_date' => 'datetime', // ou 'date', dependendo se você quer hora também
        'total_value' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
