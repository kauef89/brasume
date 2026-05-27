<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalSheet extends Model
{
    // 🚀 O Modo Elegante: Aceita tudo que o Filament mandar.
    // O array vazio diz: "Nenhum campo está bloqueado para preenchimento em massa".
    protected $guarded = [];

    // O Cast mágico que transforma o JSON do banco em Array para o PHP
    // OBS: Ajustei o nome da coluna para 'order_type' pois foi o que usamos no RelationManager.
    // Se no seu banco a coluna se chama 'scenarios', mantenha 'scenarios' aqui e no seu Form/Table!
    protected $casts = [
        'order_type' => 'array',
    ];

    // O Produto/Receita que está a ser montado
    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    // O Insumo ou Preparado que vai dentro da receita
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }
}
