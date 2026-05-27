<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Customer;

return new class extends Migration
{
    public function up(): void
    {
        // Puxa todos os clientes e limpa os campos
        $customers = Customer::all();
        
        foreach ($customers as $customer) {
            // Remove tudo o que não for número (0 a 9)
            $cleanCpf = preg_replace('/[^0-9]/', '', (string) $customer->cpf);
            $cleanPhone = preg_replace('/[^0-9]/', '', (string) $customer->phone);
            
            // Só salva se houver alteração para poupar processamento
            if ($customer->cpf !== $cleanCpf || $customer->phone !== $cleanPhone) {
                $customer->cpf = $cleanCpf;
                $customer->phone = $cleanPhone;
                $customer->save();
            }
        }
    }

    public function down(): void
    {
        // Não é possível desfazer uma limpeza de formatação
    }
};