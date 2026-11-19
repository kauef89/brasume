<?php

namespace App\Models;

use App\Enums\BankAccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CreditCard> $creditCards
 * @property-read int|null $credit_cards_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentMethod> $paymentMethods
 * @property-read int|null $payment_methods_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AccountReceivable> $accountReceivables
 * @property-read int|null $account_receivables_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount withoutTrashed()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class BankAccount extends Model
{
    use SoftDeletes;

    // Caminho livre para gravação (o Filament faz a segurança no formulário)
    protected $guarded = [];

    protected $casts = [
        'is_personal' => 'boolean',
        'type' => BankAccountType::class,
        'initial_balance' => 'float',
        'is_active' => 'boolean',

        // ==========================================
        // NOVOS CASTS: REGRAS DE RECEBIMENTO
        // ==========================================
        'debit_fee' => 'decimal:2',
        'credit_fee' => 'decimal:2',
        'anticipation_fee' => 'decimal:2',
        'debit_term_days' => 'integer',
        'credit_term_days' => 'integer',
        'anticipation_days' => 'array', // Transforma o array do Filament num JSON no banco
    ];

    public function creditCards(): HasMany
    {
        return $this->hasMany(CreditCard::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'destination_account_id');
    }

    // 🚀 A RELAÇÃO MÁGICA QUE FALTAVA PARA O RELATION MANAGER FUNCIONAR!
    public function accountReceivables(): HasMany
    {
        return $this->hasMany(AccountReceivable::class);
    }

    public function accountPayables(): HasMany
    {
        return $this->hasMany(AccountPayable::class);
    }

    // =========================================================================
    // ESCOPOS SEMÂNTICOS POR TIPO DE CONTA
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Apenas instituições bancárias reais (Corrente, Poupança, Investimento) — aptas a OFX e CNAB 240.
     */
    public function scopeBankInstitutions(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereIn('type', [
            BankAccountType::CHECKING->value,
            BankAccountType::SAVINGS->value,
            BankAccountType::INVESTMENT->value,
        ]);
    }

    /**
     * Disponibilidade imediata de tesouraria (Bancos + Cofre Físico), excluindo Cartão de Crédito e contas virtuais.
     */
    public function scopeLiquidTreasury(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereIn('type', [
            BankAccountType::CHECKING->value,
            BankAccountType::SAVINGS->value,
            BankAccountType::VAULT->value,
            BankAccountType::INVESTMENT->value,
        ]);
    }

    /**
     * Contas de movimentação financeira real (inclui Cartão de Crédito para pagamento de fatura, exclui virtuais).
     */
    public function scopeRealFunds(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotIn('type', [
            BankAccountType::REVENUE_DEDUCTION->value,
            BankAccountType::TRANSIT->value,
        ]);
    }

    /**
     * Apenas cofres físicos / gavetas de PDV.
     */
    public function scopeVaults(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('type', BankAccountType::VAULT->value);
    }
}
