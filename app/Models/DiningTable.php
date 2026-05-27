<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiningTable extends Model
{
    protected $guarded = [];

    public function tableArea(): BelongsTo
    {
        return $this->belongsTo(TableArea::class);
    }
}
