<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivedInvoice extends Model
{
    use SoftDeletes;

    protected $table = 'received_invoices';

    protected $guarded = [];

    protected $casts = [
        'issue_date'   => 'datetime',
        'total_value'  => 'decimal:2',
        'has_full_xml' => 'boolean',
    ];

    /**
     * Itens de estoque associados a esta nota fiscal via chave estrangeira
     */
    public function costHistories(): HasMany
    {
        return $this->hasMany(ProductCostHistory::class, 'received_invoice_id');
    }

    /**
     * Despesas ou compras pessoais associadas a esta nota fiscal via chave estrangeira
     */
    public function personalPurchases(): HasMany
    {
        return $this->hasMany(PersonalPurchase::class, 'received_invoice_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
