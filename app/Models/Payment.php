<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\AcquirerTransaction;

class Payment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Movimentação contábil gerada por este pagamento no livro-caixa
     */
    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    /**
     * Itens cobertos por este pagamento (baixa operacional)
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            OrderItem::class,
            'order_payment_items',
            'order_payment_id',
            'order_item_id'
        )
        ->withPivot('quantity', 'amount')
        ->withTimestamps();
    }

    // ==========================================
    // 🛡️ CONTROLE DE ESTADOS & RETROCOMPATIBILIDADE
    // ==========================================
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function acquirerTransaction(): HasOne
    {
        return $this->hasOne(AcquirerTransaction::class);
    }
}
