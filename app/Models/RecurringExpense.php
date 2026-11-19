<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read \App\Models\Supplier|null $supplier
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringExpense newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringExpense newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringExpense onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringExpense query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringExpense withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurringExpense withoutTrashed()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class RecurringExpense extends Model
{
    use HasFactory, SoftDeletes;

    // 🚀 O Laravel é inteligente: como a classe se chama RecurringExpense,
    // ele já sabe que a tabela no banco se chama recurring_expenses. Não precisamos do $table!

    protected $guarded = [];

    protected $casts = [
        "is_auto_debit"   => "boolean",
        "settlement_mode" => \App\Enums\PayableSettlementMode::class,
        "is_active"       => "boolean",
        "default_amount"  => "decimal:2",
    ];

    protected static function booted(): void
    {
        static::saving(function (RecurringExpense $expense) {
            if ($expense->isDirty('settlement_mode') && $expense->settlement_mode !== null) {
                $mode = $expense->settlement_mode instanceof \App\Enums\PayableSettlementMode
                    ? $expense->settlement_mode
                    : \App\Enums\PayableSettlementMode::tryFrom((string) $expense->settlement_mode);
                $expense->is_auto_debit = $mode === \App\Enums\PayableSettlementMode::AUTO_DEBIT;
            } elseif ($expense->isDirty('is_auto_debit')) {
                $expense->settlement_mode = $expense->is_auto_debit
                    ? \App\Enums\PayableSettlementMode::AUTO_DEBIT
                    : \App\Enums\PayableSettlementMode::MANUAL;
            } elseif (empty($expense->settlement_mode)) {
                $expense->settlement_mode = $expense->is_auto_debit
                    ? \App\Enums\PayableSettlementMode::AUTO_DEBIT
                    : \App\Enums\PayableSettlementMode::MANUAL;
            }
        });
    }

    /**
     * @return BelongsTo<Supplier,RecurringExpense>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Supplier,RecurringExpense>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
    public function accountPayables(): HasMany
    {
        return $this->hasMany(AccountPayable::class);
    }
}
