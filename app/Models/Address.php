<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'no_number' => 'boolean',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function street() { return $this->belongsTo(Street::class); }
    public function neighborhood() { return $this->belongsTo(Neighborhood::class); }
}
