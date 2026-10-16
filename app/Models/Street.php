<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Street extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    /**
     * Uma rua é formada por uma ou mais faixas/segmentos.
     */
    public function segments(): HasMany
    {
        return $this->hasMany(StreetSegment::class);
    }

    /**
     * 🚀 Bairro principal da Rua.
     * Se a tabela streets tiver neighborhood_id direto, usa BelongsTo.
     * Caso contrário, busca através dos segmentos da rua (hasOneThrough).
     */
    public function neighborhood(): BelongsTo|HasOneThrough
    {
        if (true) {
            return $this->belongsTo(Neighborhood::class);
        }

        return $this->hasOneThrough(
            Neighborhood::class,
            StreetSegment::class,
            'street_id',       // FK na tabela street_segments apontando para streets
            'id',              // PK na tabela neighborhoods
            'id',              // PK na tabela streets
            'neighborhood_id'  // FK na tabela street_segments apontando para neighborhoods
        );
    }

    /**
     * Todos os bairros cruzados pela rua (caso passe por mais de um)
     */
    public function neighborhoods(): HasManyThrough
    {
        return $this->hasManyThrough(
            Neighborhood::class,
            StreetSegment::class,
            'street_id',
            'id',
            'id',
            'neighborhood_id'
        )->distinct();
    }
}
