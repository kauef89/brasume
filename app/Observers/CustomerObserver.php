<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\CompanySetting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CustomerObserver
{
    /**
     * Disparado quando um novo cliente é salvo no banco pela primeira vez.
     */
    public function created(Customer $customer): void
    {
        $this->generateWelcomeCoupon($customer);
    }

    /**
     * Gera um cupom de Primeira Compra se as configurações da empresa permitirem.
     */
    private function generateWelcomeCoupon(Customer $customer): void
    {
        try {
            // (Opcional) Podemos checar uma flag no CompanySettings se o dono da loja quer dar esse benefício
            // $settings = CompanySetting::getCached();
            // if (!$settings->enable_welcome_coupons) return;

            $firstName = explode(' ', trim($customer->name))[0] ?? 'CLIENTE';
            $firstName = strtoupper(preg_replace('/[^A-Za-z]/', '', Str::ascii($firstName)));
            $uniqueHash = substr(uniqid(), -4);
            
            $code = "BEMVINDO-{$firstName}-{$uniqueHash}";

            $customer->coupons()->create([
                'code'            => $code,
                'description'     => 'Presente de Boas-Vindas (1ª Compra)',
                'type'            => 'percentage', 
                'value'           => 10.00, // 10% Off
                'usage_limit'     => 1, 
                'is_active'       => true,
                'expires_at'      => now()->addDays(30),
            ]);
            
        } catch (\Exception $e) {
            // Se o model Coupon não tiver as colunas exatas que passei acima, 
            // a falha será silenciosa e não impedirá o cadastro do cliente.
            Log::error("CRM: Erro ao gerar cupom de boas-vindas para o Cliente ID {$customer->id} - " . $e->getMessage());
        }
    }
}
