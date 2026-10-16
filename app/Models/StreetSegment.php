<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Neighborhood|null $neighborhood
 * @property-read \App\Models\Street|null $street
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreetSegment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreetSegment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreetSegment query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class StreetSegment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'start_number' => 'integer',
        'end_number' => 'integer',
    ];

    public function street()
    {
        return $this->belongsTo(Street::class);
    }

    public function neighborhood()
    {
        return $this->belongsTo(Neighborhood::class);
    }
}
