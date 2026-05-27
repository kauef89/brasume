<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountPayable extends Model
{
    protected $guarded = [];

    protected $casts = [
        "due_date" => "date",
        "payment_date" => "date",
        "amount" => "decimal:2",
    ];

    // ==========================================
    // 🚀 A MÁGICA: Gatilhos Automáticos do Laravel
    // ==========================================
    protected static function booted()
    {
        // 1. GATILHO DE CRIAÇÃO (Quando a conta é salva pela 1ª vez)
        static::created(function ($payable) {
            if ($payable->employee_id) {
                $desc = strtolower($payable->description);
                // O Sistema lê a descrição para entender o contexto
                $isVale =
                    str_contains($desc, "vale") ||
                    str_contains($desc, "adiantamento");

                // Se NÃO for um vale, entendemos que o colaborador prestou um serviço.
                // Então a empresa passa a DEVER esse valor para ele (Crédito na conta corrente).
                if (!$isVale) {
                    $payable->launchLedger(
                        "credit",
                        "Provisão de Serviço: " . $payable->description,
                    );
                }

                // Se por acaso você criar a conta já com o status 'Pago' direto no form
                if ($payable->status === "paid") {
                    $payable->launchLedger(
                        "debit",
                        "Pagamento: " . $payable->description,
                    );
                }
            }
        });

        // 2. GATILHO DE ATUALIZAÇÃO (Quando você clica em Baixar ou Concilia no Banco)
        static::updated(function ($payable) {
            if ($payable->employee_id && $payable->isDirty("status")) {
                // Se mudou para PAGO (Baixado) -> Debita do extrato
                if ($payable->status === "paid") {
                    $payable->launchLedger(
                        "debit",
                        "Pagamento: " . $payable->description,
                    );
                }

                // Se alguém errou e ESTORNOU a conta (Voltou de pago para pendente) -> Devolve o saldo
                if (
                    $payable->status === "pending" &&
                    $payable->getOriginal("status") === "paid"
                ) {
                    $payable->launchLedger(
                        "credit",
                        "Estorno de Pagamento: " . $payable->description,
                    );
                }
            }
        });
    }

    // ==========================================
    // FUNÇÃO AUXILIAR: Insere a linha no Extrato do Colaborador
    // ==========================================
    public function launchLedger($type, $description)
    {
        $employee = $this->employee;
        if (!$employee) {
            return;
        }

        // Descobre o saldo atual do colaborador
        $latestLedger = $employee->ledgers()->latest("id")->first();
        $currentBalance = $latestLedger ? (float) $latestLedger->balance : 0;

        $amount = (float) $this->amount;

        // Se for crédito, soma. Se for débito (pagamento), subtrai.
        $newBalance =
            $type === "credit"
                ? $currentBalance + $amount
                : $currentBalance - $amount;

        // Salva a nova linha na Conta Corrente
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
}
