<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Kitchen> $kitchens
 * @property-read int|null $kitchens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Printer withoutTrashed()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Printer extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_cashier' => 'boolean',
    ];

    /**
     * 🚀 MÁGICA: Eventos do Model
     * Garante que se eu marcar esta impressora como "Caixa",
     * o sistema desmarca automaticamente as outras.
     */
    protected static function booted()
    {
        static::saving(function ($printer) {
            if ($printer->is_cashier) {
                Printer::where('id', '!=', $printer->id)->update(['is_cashier' => false]);
            }
        });
    }

    public function kitchens()
    {
        return $this->hasMany(Kitchen::class);
    }
}
