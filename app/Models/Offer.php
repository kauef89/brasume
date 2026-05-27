<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'promo_price' => 'decimal:2',
    ];

    public function triggerProduct()
    {
        return $this->belongsTo(Product::class, 'trigger_product_id');
    }
}
