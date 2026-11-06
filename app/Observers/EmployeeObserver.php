<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class EmployeeObserver
{
    /**
     * Disparado quando um colaborador é criado no RH.
     */
    public function created(Employee $employee): void
    {
        $this->syncToCustomer($employee);
        $this->syncToUser($employee);
    }

    /**
     * Disparado quando os dados de um colaborador são atualizados no RH.
     */
    public function updated(Employee $employee): void
    {
        $this->syncToCustomer($employee);
        $this->syncToUser($employee);
    }

    /**
     * Quando o colaborador for desativado/demitido, desvincula consumo interno e suspende o acesso ao sistema.
     */
    public function deleted(Employee $employee): void
    {
        Customer::where('employee_id', $employee->id)->update([
            'employee_id' => null,
        ]);

        User::where('employee_id', $employee->id)->update([
            'is_active' => false,
        ]);
    }

    /**
     * Sincroniza ou cria o registro correspondente na tabela customers (Consumo Interno).
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

    /**
     * Espelha os cargos operacionais do Employee para as Roles do Spatie no User.
     */
    protected function syncToUser(Employee $employee): void
    {
        try {
            // 1. Localiza a conta de acesso vinculada
            $user = $employee->user ?: User::where('employee_id', $employee->id)->first();

            // 2. Fallback: Localiza por e-mail e estabelece o vínculo caso ainda não exista
            if (!$user && !empty($employee->email)) {
                $user = User::where('email', $employee->email)->first();
                if ($user && empty($user->employee_id)) {
                    $user->update(['employee_id' => $employee->id]);
                }
            }

            if (!$user) {
                return;
            }

            // 3. Sincroniza status de ativação e nome
            $userUpdates = [];
            if ($user->name !== $employee->name && !empty($employee->name)) {
                $userUpdates['name'] = $employee->name;
            }
            if ((bool) $user->is_active !== (bool) $employee->is_active) {
                $userUpdates['is_active'] = (bool) $employee->is_active;
            }

            if (!empty($userUpdates)) {
                $user->update($userUpdates);
            }

            // 4. Espelhamento direto: Employee -> Spatie Roles
            if (method_exists($employee, 'getAllRoles')) {
                $roles = $employee->getAllRoles();

                if (!empty($roles)) {
                    $guard = $user->guard_name ?? config('auth.defaults.guard', 'web');

                    foreach ($roles as $roleName) {
                        Role::findOrCreate($roleName, $guard);
                    }

                    $user->syncRoles($roles);
                }
            }
        } catch (\Throwable $e) {
            Log::error("Erro ao sincronizar Colaborador ID {$employee->id} para Usuário: " . $e->getMessage());
        }
    }
}
