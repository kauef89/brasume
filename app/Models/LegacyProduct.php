<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyProduct extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_ignored' => 'boolean',
    ];

    // O link mágico para o presente
    public function currentProduct()
    {
        return $this->belongsTo(Product::class, "current_product_id");
    }
    public function currentModifier()
    {
        return $this->belongsTo(
            \Illuminate\Support\Facades\DB::table(
                "modifier_options",
            )->getGrammar()
                ? \stdClass::class
                : \stdClass::class,
            "current_modifier_id",
        );
    }
}
