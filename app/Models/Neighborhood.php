<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Neighborhood extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'delivery_fee' => 'float',
    ];

    /**
     * Um bairro tem vários pedaços (segmentos) de ruas cruzando por ele.
     */
    public function segments(): HasMany
    {
        return $this->hasMany(StreetSegment::class);
    }

    // Alias para não quebrar chamadas legadas
    public function neighborhood(): HasMany
    {
        return $this->segments();
    }
}
