<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Street extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    /**
     * Uma rua é formada por uma ou mais faixas/segmentos.
     */
    public function segments()
    {
        return $this->hasMany(StreetSegment::class);
    }
}
