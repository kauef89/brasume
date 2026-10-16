<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Customer|null $customer
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyCustomer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyCustomer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyCustomer query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class LegacyCustomer extends Model
{
    protected $guarded = [];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
