<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_trusted'  => 'boolean',
            'is_personal' => 'boolean',
            'is_active'   => 'boolean',
            'order_days'  => 'array',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'parent_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Supplier::class, 'parent_id');
    }

    public function accountPayables(): HasMany
    {
        return $this->hasMany(\App\Models\AccountPayable::class, 'supplier_id');
    }

    public function costHistories(): HasMany
    {
        return $this->hasMany(ProductCostHistory::class, 'supplier_id');
    }

    public function suppliedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_cost_histories', 'supplier_id', 'product_id')
            ->distinct();
    }

    public function getFamilyIdsAttribute(): array
    {
        $matrixId = $this->parent_id ?? $this->id;
        $branchIds = self::where('parent_id', $matrixId)->pluck('id')->toArray();
        return array_unique(array_merge([$matrixId], $branchIds));
    }

    protected static function booted()
    {
        static::saving(function ($supplier) {
            if (!empty($supplier->document)) {
                $exists = self::where('document', $supplier->document)
                    ->where('id', '!=', $supplier->id)
                    ->exists();

                if ($exists) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'document' => "O CNPJ/CPF '{$supplier->document}' já está em uso por outro fornecedor na base de dados."
                    ]);
                }
            }
        });

        static::creating(function ($supplier) {
            if (!empty($supplier->parent_id) && !isset($supplier->is_trusted)) {
                $parent = self::find($supplier->parent_id);
                if ($parent && (bool) $parent->is_trusted) {
                    $supplier->is_trusted = true;
                }
            }
        });

        static::saved(function ($supplier) {
            if ($supplier->wasChanged('is_trusted')) {
                $supplier->branches()->update(['is_trusted' => (bool) $supplier->is_trusted]);
            }
        });
    }
}
