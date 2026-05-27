<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableArea extends Model
{
    protected $fillable = ['name'];

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }
}