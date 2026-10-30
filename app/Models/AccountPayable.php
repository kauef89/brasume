<?php

namespace App\Models;

use App\Observers\AccountPayableObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([AccountPayableObserver::class])]
class AccountPayable extends Model
{
    protected $guarded = [];

    protected $casts = [
        "due_date" => "date",
        "payment_date" => "date",
        "amount" => "decimal:2",
    ];

    // ==========================================
    // FUNÇÃO AUXILIAR: Insere a linha no Extrato do Colaborador
    // ==========================================
    public function launchLedger($type, $description)
    {
        $employee = $this->employee;
        if (!$employee) {
            return;
        }

        $latestLedger = $employee->ledgers()->latest("id")->first();
        $currentBalance = $latestLedger ? (float) $latestLedger->balance : 0;
        $amount = (float) $this->amount;

        $newBalance = $type === "credit"
            ? $currentBalance + $amount
            : $currentBalance - $amount;

        $employee->ledgers()->create([
            "type" => $type,
            "description" => $description,
            "amount" => $amount,
            "balance" => $newBalance,
        ]);
    }

    // ==========================================
    // RELAÇÕES
    // ==========================================
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function bankStatement()
    {
        return $this->morphOne(\App\Models\BankStatement::class, "conciliable");
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

        public function recurringExpense()
    {
        return $this->belongsTo(RecurringExpense::class, 'recurring_expense_id');
    }

    public function shiftAttendance()
    {
        return $this->hasOne(ShiftAttendance::class, 'account_payable_id');
    }

    public function schedule()
    {
        return $this->hasOne(EmployeeSchedule::class, 'payable_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
