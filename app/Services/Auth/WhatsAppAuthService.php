<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class WhatsAppAuthService
{
    /**
     * Gera o OTP e simula o envio via WhatsApp (Cache)
     */
    public function sendOtp(string $phone): void
    {
        $cleanPhone = preg_replace('/\D/', '', $phone);
        $code = (string) rand(100000, 999999);

        Cache::put("whatsapp_otp_{$cleanPhone}", $code, now()->addMinutes(5));

        // Aqui entra a sua API do WhatsApp (Z-API, Evolution, etc.)
        Log::info("🔑 TOKEN OTP GERADO PARA {$cleanPhone}: {$code}");
    }

    /**
     * Valida o OTP, cria/atualiza o cliente e retorna a instância de User
     */
    public function verifyOtpAndAuthenticate(string $phone, string $code, ?array $socialData = null): User
    {
        $cleanPhone = preg_replace('/\D/', '', $phone);
        $cachedCode = Cache::get("whatsapp_otp_{$cleanPhone}");

        if (!$cachedCode || $cachedCode !== $code) {
            throw new Exception('Código de verificação inválido ou expirado.');
        }

        Cache::forget("whatsapp_otp_{$cleanPhone}");

        $customer = Customer::where('phone', $cleanPhone)->first();

        return DB::transaction(function () use ($customer, $cleanPhone, $socialData) {
            if ($customer && $customer->user_id) {
                return $customer->user;
            }

            $email = $socialData['email'] ?? "{$cleanPhone}@pastelarianativa.com.br";
            $name = $socialData['name'] ?? ($customer->name ?? 'Cliente Nativa');

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => bcrypt(Str::random(16)),
                'is_active' => true
            ]);

            if ($customer) {
                $customer->update(['user_id' => $user->id, 'email' => $email]);
            } else {
                Customer::create([
                    'user_id' => $user->id,
                    'name' => $name,
                    'phone' => $cleanPhone,
                    'email' => $email,
                    'is_active' => true
                ]);
            }

            return $user;
        });
    }
}
