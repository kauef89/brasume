<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function parent()
    {
        return $this->belongsTo(Supplier::class, 'parent_id');
    }

    public function branches()
    {
        return $this->hasMany(Supplier::class, 'parent_id');
    }

    public function accountPayables()
    {
        return $this->hasMany(\App\Models\AccountPayable::class, 'supplier_id');
    }
}
