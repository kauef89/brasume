<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
