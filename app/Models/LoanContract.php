<?php

namespace App\Models;

use App\Enums\AccountingCategory;
use App\Enums\AccountingSubcategory;
use App\Enums\PayableSettlementMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class LoanContract extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'principal_amount'       => 'decimal:2',
        'origination_fee_amount' => 'decimal:2',
        'net_disbursed_amount'   => 'decimal:2',
        'installments_count'     => 'integer',
        'installment_amount'     => 'decimal:2',
        'total_payable_amount'   => 'decimal:2',
        'total_interest_amount'  => 'decimal:2',
        'monthly_interest_rate'  => 'decimal:4',
        'disbursement_date'      => 'date',
        'first_due_date'         => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (LoanContract $contract) {
            $principal = round((float) $contract->principal_amount, 2);
            $fee = round((float) ($contract->origination_fee_amount ?? 0), 2);
            $count = max(1, (int) $contract->installments_count);
            $instAmt = round((float) $contract->installment_amount, 2);

            $totalPayable = round($count * $instAmt, 2);
            $totalInterest = max(0.0, round($totalPayable - $principal, 2));
            $netDisbursed = (float) $contract->net_disbursed_amount > 0
                ? round((float) $contract->net_disbursed_amount, 2)
                : max(0.0, round($principal - $fee, 2));

            $contract->total_payable_amount = $totalPayable;
            $contract->total_interest_amount = $totalInterest;
            $contract->net_disbursed_amount = $netDisbursed;

            if (empty($contract->monthly_interest_rate) && $principal > 0 && $totalInterest > 0) {
                $contract->monthly_interest_rate = round((($totalInterest / $principal) / $count) * 100, 4);
            }
        });

        static::created(function (LoanContract $contract) {
            $contract->generateScheduleAndPayables();
        });

        static::deleting(function (LoanContract $contract) {
            foreach ($contract->installments as $inst) {
                if ($inst->account_payable_id && $inst->status === 'pending') {
                    AccountPayable::where('id', $inst->account_payable_id)
                        ->where('status', 'pending')
                        ->delete();
                }
            }
        });
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function disbursementStatement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'disbursement_statement_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('installment_number');
    }

    /**
     * Gera o cronograma de parcelas dividindo Principal (amortização patrimonial) vs Juros (encargo financeiro)
     * e cria os títulos correspondentes no Contas a Pagar como Débito Automático.
     */
    public function generateScheduleAndPayables(): void
    {
        if ($this->installments()->exists()) {
            return;
        }

        DB::transaction(function () {
            $count = max(1, (int) $this->installments_count);
            $principalTotal = round((float) $this->principal_amount, 2);
            $installmentTotal = round((float) $this->installment_amount, 2);
            $totalPayable = round($count * $installmentTotal, 2);
            $interestTotal = max(0.0, round($totalPayable - $principalTotal, 2));

            $basePrincipal = floor(($principalTotal / $count) * 100) / 100;
            $baseInterest = floor(($interestTotal / $count) * 100) / 100;

            $principalAcc = 0.0;
            $interestAcc = 0.0;

            $firstDue = Carbon::parse($this->first_due_date);
            $contractCode = strtoupper(trim((string) $this->contract_number));

            for ($i = 1; $i <= $count; $i++) {
                $dueDate = $firstDue->copy()->addMonthsNoOverflow($i - 1);

                if ($i === $count) {
                    $principalPart = round($principalTotal - $principalAcc, 2);
                    $interestPart = max(0.0, round($installmentTotal - $principalPart, 2));
                } else {
                    $principalPart = min($installmentTotal, $basePrincipal);
                    $interestPart = max(0.0, round($installmentTotal - $principalPart, 2));
                    $principalAcc = round($principalAcc + $principalPart, 2);
                    $interestAcc = round($interestAcc + $interestPart, 2);
                }

                $installment = LoanInstallment::create([
                    'loan_contract_id'   => $this->id,
                    'installment_number' => $i,
                    'due_date'           => $dueDate->toDateString(),
                    'total_amount'       => $installmentTotal,
                    'principal_portion'  => $principalPart,
                    'interest_portion'   => $interestPart,
                    'status'             => 'pending',
                ]);

                $desc = "Parcela {$i}/{$count} - {$this->description} (Contrato {$contractCode})";
                $obs = sprintf(
                    'Contrato %s (%s) | Parcela %d/%d — Amortização Principal: R$ %s | Juros/Encargos: R$ %s.',
                    $contractCode,
                    $this->lender_name,
                    $i,
                    $count,
                    number_format($principalPart, 2, ',', '.'),
                    number_format($interestPart, 2, ',', '.')
                );

                $payable = AccountPayable::create([
                    'loan_installment_id'    => $installment->id,
                    'description'            => $desc,
                    'installment'            => "{$i}/{$count}",
                    'amount'                 => $installmentTotal,
                    'due_date'               => $dueDate->toDateString(),
                    'status'                 => 'pending',
                    'settlement_mode'        => PayableSettlementMode::AUTO_DEBIT->value,
                    'is_personal'            => false,
                    'accounting_category'    => AccountingCategory::FINANCIAL_EXPENSES->value,
                    'accounting_subcategory' => AccountingSubcategory::LOAN_INTEREST->value,
                    'observations'           => $obs,
                ]);

                $installment->updateQuietly(['account_payable_id' => $payable->id]);

                // Se já houver parcela deste contrato conciliada anteriormente no extrato bancário (cadastro retroativo),
                // vincula sem gerar nova transação bancária para evitar duplicidade de saldo.
                $alreadyPaidStmt = BankStatement::where('bank_account_id', $this->bank_account_id)
                    ->where('status', 'reconciled')
                    ->where('amount', '<', 0)
                    ->whereBetween('bank_date', [
                        $dueDate->copy()->subDays(5)->toDateString(),
                        $dueDate->copy()->addDays(5)->toDateString(),
                    ])
                    ->whereRaw('ABS(ABS(amount) - ?) < 0.05', [$installmentTotal])
                    ->where(function ($q) use ($contractCode) {
                        $q->where('statement_identifier', $contractCode)
                          ->orWhere('description', 'like', "%{$contractCode}%");
                    })
                    ->whereNotIn('id', LoanInstallment::whereNotNull('bank_statement_id')->pluck('bank_statement_id'))
                    ->first();

                if ($alreadyPaidStmt) {
                    $installment->updateQuietly([
                        'status'            => 'paid',
                        'paid_amount'       => abs((float) $alreadyPaidStmt->amount),
                        'payment_date'      => $alreadyPaidStmt->bank_date,
                        'bank_statement_id' => $alreadyPaidStmt->id,
                    ]);

                    $payable->updateQuietly([
                        'status'          => 'paid',
                        'payment_date'    => $alreadyPaidStmt->bank_date,
                        'bank_account_id' => $this->bank_account_id,
                        'transaction_id'  => $alreadyPaidStmt->transaction_id,
                        'observations'    => $obs . "\n[Vinculado automaticamente ao extrato histórico #{$alreadyPaidStmt->id}.]",
                    ]);
                }
            }

            $this->refreshStatusFromInstallments();
        });
    }

    public function refreshStatusFromInstallments(): void
    {
        $hasPending = $this->installments()->where('status', '!=', 'paid')->exists();
        $newStatus = $hasPending ? 'active' : 'paid_off';
        if ($this->status !== $newStatus) {
            $this->updateQuietly(['status' => $newStatus]);
        }
    }
}