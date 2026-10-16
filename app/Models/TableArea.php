<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableArea extends Model
{
    protected $guarded = [];

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }
}
