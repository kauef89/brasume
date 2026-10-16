<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\User|null $authorizer
 * @property-read \App\Models\User|null $operator
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosAudit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosAudit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PosAudit query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class PosAudit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
    ];

    // Quem estava a operar o caixa
    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    // O gerente que colocou a senha (ou o próprio operador se ele tiver permissão)
    public function authorizer()
    {
        return $this->belongsTo(User::class, 'authorizer_id');
    }
}
