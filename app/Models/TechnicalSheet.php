<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalSheet extends Model
{
    protected $guarded = [];

    protected $casts = [
        'order_type'        => 'array',
        'consumption_scope' => \App\Enums\ConsumptionChannelScope::class,
        'is_active'         => 'boolean',
        'is_dine_in'        => 'boolean',
        'is_takeaway'       => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (TechnicalSheet $sheet) {
            if ($sheet->isDirty('consumption_scope') && $sheet->consumption_scope !== null) {
                $scope = $sheet->consumption_scope instanceof \App\Enums\ConsumptionChannelScope
                    ? $sheet->consumption_scope
                    : (\App\Enums\ConsumptionChannelScope::tryFrom((string) $sheet->consumption_scope) ?? \App\Enums\ConsumptionChannelScope::ALL);

                $sheet->is_dine_in = $scope->appliesToDineIn();
                $sheet->is_takeaway = $scope->appliesToTakeaway();
            } elseif ($sheet->isDirty('is_dine_in') || $sheet->isDirty('is_takeaway') || empty($sheet->consumption_scope)) {
                $dineIn = $sheet->is_dine_in ?? true;
                $takeaway = $sheet->is_takeaway ?? true;
                $sheet->consumption_scope = \App\Enums\ConsumptionChannelScope::fromFlags((bool) $dineIn, (bool) $takeaway);
            }
        });
    }

    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }
}
