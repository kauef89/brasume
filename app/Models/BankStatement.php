<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    use HasFactory;

    // 🚀 Liberamos a atribuição em massa (O Filament já garante a segurança dos dados)
    protected $guarded = [];

    // Formata automaticamente a data para o padrão do Laravel
    protected $casts = [
        "bank_date" => "date",
        "amount" => "decimal:2",
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
}
