<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'acquisition_cost' => 'decimal:2',
        'acquisition_date' => 'date',
        'warranty_until'   => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getFormattedCostAttribute(): string
    {
        return 'R$ ' . number_format((float) $this->acquisition_cost, 2, ',', '.');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active'         => 'Ativo / Em Uso',
            'in_maintenance' => 'Em Manutenção',
            'written_off'    => 'Baixado / Descartado',
            default          => ucfirst($this->status),
        };
    }
}
