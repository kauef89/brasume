<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductModifier extends Model // <-- AQUI ESTAVA O ERRO! (A classe tem de ter o mesmo nome do arquivo)
{
    use HasFactory, SoftDeletes;

    // Como mudámos o nome do Model, temos de avisar o Laravel que o nome
    // da tabela no banco de dados continua a ser o antigo ('modifier_groups')
    protected $table = 'modifier_groups';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_quantity' => 'boolean',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'max_free' => 'integer',
    ];

    public function options()
    {
        return $this->hasMany(ModifierOption::class, 'modifier_group_id');
    }

    public function products()
    {
        // Aqui também garantimos que ele aponte para a tabela pivot correta: 'product_modifier'
        return $this->belongsToMany(Product::class, 'product_modifier', 'modifier_id', 'product_id')
                    ->withPivot('sort_order');
    }
}
