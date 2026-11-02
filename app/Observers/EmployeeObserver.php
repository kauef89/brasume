<?php

namespace App\Observers;

use App\Models\Employee;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

class EmployeeObserver
{
    /**
     * Disparado quando um colaborador é criado no RH.
     */
    public function created(Employee $employee): void
    {
        $this->syncToCustomer($employee);
    }

    /**
     * Disparado quando os dados de um colaborador são atualizados no RH.
     */
    public function updated(Employee $employee): void
    {
        $this->syncToCustomer($employee);
    }

    /**
     * Quando o colaborador for desativado/demitido, desvincula o acesso a consumo interno.
     */
    public function deleted(Employee $employee): void
    {
        Customer::where('employee_id', $employee->id)->update([
            'employee_id' => null,
        ]);
    }

    /**
     * Sincroniza ou cria o registro correspondente na tabela customers.
     */
    protected function syncToCustomer(Employee $employee): void
    {
        try {
            $cleanCpf = preg_replace('/\D/', '', $employee->cpf ?? '');
            $cleanPhone = preg_replace('/\D/', '', $employee->phone ?? '');

            // 1. Tenta localizar por vínculo direto de employee_id
            $customer = Customer::where('employee_id', $employee->id)->first();

            // 2. Fallback: Localiza por CPF
            if (!$customer && !empty($cleanCpf)) {
                $customer = Customer::where('cpf', $cleanCpf)->first();
            }

            // 3. Fallback: Localiza por Telefone
            if (!$customer && !empty($cleanPhone)) {
                $customer = Customer::where('phone', $cleanPhone)->first();
            }

            // 4. Fallback: Localiza por E-mail
            if (!$customer && !empty($employee->email)) {
                $customer = Customer::where('email', $employee->email)->first();
            }

            $payload = [
                'employee_id' => $employee->id,
                'name'        => $employee->name,
                'phone'       => $cleanPhone ?: ($customer?->phone ?? '47999999999'),
                'cpf'         => $cleanCpf ?: $customer?->cpf,
                'email'       => $employee->email ?: $customer?->email,
            ];

            if ($customer) {
                $customer->update($payload);
            } else {
                Customer::create($payload);
            }
        } catch (\Throwable $e) {
            Log::error("Erro ao sincronizar Colaborador ID {$employee->id} para Clientes: " . $e->getMessage());
        }
    }
}
