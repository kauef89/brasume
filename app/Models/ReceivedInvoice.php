<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceivedInvoice extends Model
{
    // Força o Laravel a usar o nome exato da tabela
    protected $table = 'received_invoices';

    protected $guarded = [];

    protected $casts = [
        'issue_date' => 'datetime',
        'total_value' => 'decimal:2',
    ];

    /**
     * Itens enviados para o estoque
     */
    public function costHistories()
    {
        return $this->hasMany(\App\Models\ProductCostHistory::class, 'nfe_key', 'nfe_key');
    }

    /**
     * Itens enviados para compras pessoais/despesas
     */
    public function personalPurchases()
    {
        return $this->hasMany(\App\Models\PersonalPurchase::class, 'nfe_key', 'nfe_key');
    }
}
