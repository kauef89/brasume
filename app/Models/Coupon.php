<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        "is_active" => "boolean",
        "first_order_only" => "boolean",
        "starts_at" => "datetime",
        "expires_at" => "datetime",
        "value" => "decimal:2",
        "min_subtotal" => "decimal:2",
        // O Laravel converte automaticamente o array do Filament para JSON no banco e vice-versa!
        "restricted_categories" => "array",
    ];
}
