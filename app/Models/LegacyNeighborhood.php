<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyNeighborhood extends Model
{
    protected $guarded = [];

    // 🚀 Relação corrigida para a tabela neighborhoods da V3
    public function neighborhood()
    {
        return $this->belongsTo(Neighborhood::class);
    }
}
