<?php

namespace App\Services\Orders;

use App\Enums\PaymentEngineType;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Order;
use App\Models\PaymentMethod;
use Exception;

class PaymentEligibilityService
{
    /**
     * Valida se todas as formas de pagamento submetidas são elegíveis para o cliente e contexto.
     *
     * @throws Exception
     */
    public function validate(Order $order, array $payments): void
    {
        $customer = $order->customer;
        $settings = CompanySetting::getCached() ?? CompanySetting::first();

        foreach ($payments as $p) {
            $paymentMethod = PaymentMethod::find($p['method_id'] ?? null);
            if (!$paymentMethod) continue;

            $engine = $paymentMethod->engine_type instanceof PaymentEngineType
                ? $paymentMethod->engine_type->value
                : (string) $paymentMethod->engine_type;

            // 1. Vale-Alimentação: exclusivo do fluxo de lanche da equipe
            if ($engine === PaymentEngineType::ALLOWANCE->value || $engine === 'allowance') {
                throw new Exception("A forma de pagamento Vale-Alimentação é restrita ao lanche da equipe.");
            }

            // 2. Convênio: exige cliente associado a um fornecedor / empresa conveniada
            if ($engine === PaymentEngineType::CUSTOMER_CREDIT->value || $engine === 'customer_credit') {
                $hasSupplier = $customer && (!empty($customer->supplier_id) || (method_exists($customer, 'supplier') && $customer->supplier()->exists()));
                if (!$hasSupplier) {
                    throw new Exception("A forma de pagamento Convênio exige que o cliente esteja vinculado a uma empresa ou fornecedor conveniado.");
                }
            }

            // 3. Consumo Interno: exige que o cliente seja um colaborador
            if ($engine === PaymentEngineType::INTERNAL->value || $engine === 'internal') {
                if (!$customer) {
                    throw new Exception("A forma de pagamento Consumo Interno exige a identificação do colaborador no pedido.");
                }

                $employee = null;
                if (!empty($customer->employee_id) || (method_exists($customer, 'employee') && $customer->employee()->exists())) {
                    $employee = $customer->employee;
                }

                if (!$employee) {
                    $cleanCpf = preg_replace('/\D/', '', $customer->cpf ?? '');
                    $employee = Employee::where(function ($q) use ($cleanCpf, $customer) {
                        if (!empty($cleanCpf)) $q->where('cpf', $cleanCpf);
                        if (!empty($customer->email)) $q->orWhere('email', $customer->email);
                    })->first();
                }

                if (!$employee && auth()->user() && auth()->user()->employee_id) {
                    $employee = Employee::find(auth()->user()->employee_id);
                }

                if (!$employee) {
                    throw new Exception("O cliente não possui um cadastro de Colaborador válido para utilizar Consumo Interno.");
                }

                $allowTab = (bool) ($settings->allow_employee_tab ?? true);
                $latestLedger = $employee->ledgers()->latest('id')->first();
                $currentBalance = $latestLedger ? (float) $latestLedger->balance : 0;

                if (!$allowTab && $currentBalance < (float) $p['amount'] - 0.01) {
                    throw new Exception("Saldo insuficiente na carteira e o desconto em folha não está autorizado. Saldo atual: R$ " . number_format($currentBalance, 2, ',', '.'));
                }
            }
        }
    }
}
