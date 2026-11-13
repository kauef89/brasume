<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read \App\Models\BankAccount|null $bankAccount
 * @property-read Model|\Eloquent $conciliable
 * @property-read mixed $related_name
 * @property-read \App\Models\Transaction|null $transaction
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankStatement query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class BankStatement extends Model
{
    use SoftDeletes;


    public function suggestedPayable(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\AccountPayable::class, 'suggested_payable_id');
    }
    use HasFactory;

    // 🚀 Liberamos a atribuição em massa (O Filament já garante a segurança dos dados)
    protected $guarded = [];

    // Formata automaticamente a data para o padrão do Laravel
    protected $casts = [
        "bank_date" => "date",
        "amount" => "decimal:2",
        "reconciled_at" => "datetime",
    ];

    // ==========================================
    // RELAÇÕES
    // ==========================================

    // A qual conta bancária este extrato pertence
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    // Uma linha do extrato bancário pode estar ligada a UMA transação real no sistema
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    // A Mágica do Motor de Conciliação: permite ligar o extrato a Contas, Folhas de Pagamento, etc.
    public function conciliable()
    {
        return $this->morphTo();
    }

    // ==========================================
    // ATRIBUTOS VIRTUAIS
    // ==========================================

    // 🚀 ATRIBUTO VIRTUAL INTELIGENTE: Descobre quem foi pago (Conta Avulsa ou Lote)
    public function getRelatedNameAttribute()
    {
        if (!$this->conciliable) {
            return "-"; // Se não estiver conciliado, retorna um tracinho
        }

        // Se conciliado com Pagamento ou Pedido do PDV
        if ($this->conciliable_type === \App\Models\Payment::class) {
            $order = $this->conciliable->order;
            if ($order) {
                $cust = $order->customer_name ?: 'Consumidor';
                return "Pedido #{$order->id} • {$cust}";
            }
            return "Pagamento #{$this->conciliable_id}";
        }

        if ($this->conciliable_type === \App\Models\Order::class) {
            $cust = $this->conciliable->customer_name ?: 'Consumidor';
            return "Pedido #{$this->conciliable_id} • {$cust}";
        }

        // Se pagou uma conta avulsa, pega o nome do fornecedor (Sem o trade_name antigo!)
        if ($this->conciliable_type === \App\Models\AccountPayable::class) {
            return $this->conciliable->supplier->name ?? "Fornecedor S/N";
        }

        // Se pagou um lote, pega a descrição do lote
        if ($this->conciliable_type === \App\Models\PaymentBatch::class) {
            return "📦 Lote: " . $this->conciliable->description;
        }

        return "Outros";
    }

    public function suggestedReceivable(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\AccountReceivable::class, 'suggested_receivable_id');
    }
}
