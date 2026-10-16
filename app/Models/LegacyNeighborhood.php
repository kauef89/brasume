<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Neighborhood|null $neighborhood
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyNeighborhood newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyNeighborhood newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegacyNeighborhood query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class LegacyNeighborhood extends Model
{
    protected $guarded = [];

    // 🚀 Relação corrigida para a tabela neighborhoods da V3
    public function neighborhood()
    {
        return $this->belongsTo(Neighborhood::class);
    }
}
