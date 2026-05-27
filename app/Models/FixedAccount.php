<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAccount extends Model
{
    use HasFactory, SoftDeletes;

    // 🚀 Substituímos o $fillable pelo $guarded para aceitar a nova coluna "statement_identifier" automaticamente
    protected $guarded = [];

    // 🚀 MANTEMOS O CASTS! Ele é vital para os botões Toggle (boolean) do Filament funcionarem!
    protected $casts = [
        "is_auto_debit" => "boolean",
        "is_active" => "boolean",
        "default_amount" => "decimal:2",
    ];

    /**
     * @return BelongsTo<Supplier,FixedAccount>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
