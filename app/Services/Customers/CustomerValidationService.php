<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CompanySetting;

class CustomerValidationService
{
    public function checkCpfAvailability(string $rawCpf, SerproService $serpro, ?string $birthDate = null): array
    {
        $cpf = preg_replace('/[^0-9]/', '', $rawCpf);

        // 1. Validação Algorítmica Local do CPF (Módulo 11)
        if (!$this->isValidCpf($cpf)) {
            return [
                'available' => false,
                'invalid_cpf' => true,
                'message' => 'O número de CPF informado contém erros de digitação.'
            ];
        }

        // 2. Verifica duplicidade na base de clientes da empresa
        $customer = Customer::where('cpf', $cpf)->first();

        if ($customer) {
            $nameParts = explode(' ', $customer->name);
            $maskedName = $nameParts[0];
            if (count($nameParts) > 1) {
                $maskedName .= ' ' . mb_substr(end($nameParts), 0, 1) . '***';
            }

            return [
                'available'    => false,
                'cpf_exists'   => true,
                'masked_name'  => $maskedName,
                'masked_phone' => $customer->phone ? '(**) *****-' . mb_substr($customer->phone, -4) : null,
            ];
        }

        $settings = CompanySetting::getCached();
        $isSerproActive = $settings
            && $settings->customer_registration_mode === 'serpro_magic'
            && !empty($settings->serpro_client_id)
            && !empty($settings->serpro_client_secret);

        // 3. Se Serpro estiver ligado E a data de nascimento for fornecida (Regra Serpro v3)
        if ($isSerproActive && !empty($birthDate)) {
            try {
                $data = $serpro->consultCpf($cpf, $birthDate);
                return [
                    'available'  => true,
                    'used_api'   => true,
                    'name'       => $data['nome'] ?? '',
                    'birth_date' => $data['nascimento'] ?? null,
                ];
            } catch (\Exception $e) {
                return ['available' => true, 'used_api' => false, 'error' => $e->getMessage()];
            }
        }

        // 4. Modo Ágil / Serpro Desligado
        return ['available' => true, 'used_api' => false];
    }

    public function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match("/^{$cpf[0]}{11}$/", $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }
}
