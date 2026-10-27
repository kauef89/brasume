<?php

namespace App\Models;

use App\Enums\BankAccountType;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany; // 🚀 Import da relação

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
}
