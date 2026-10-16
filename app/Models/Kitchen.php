<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read \App\Models\Printer|null $printer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kitchen newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kitchen newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kitchen onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kitchen query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kitchen withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kitchen withoutTrashed()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Kitchen extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // 🚀 OS COLABORADORES QUE TÊM ACESSO A ESTA PRAÇA
    public function employees()
    {
        return $this->belongsToMany(Employee::class);
    }
}
