<?php

namespace App\Services\Auth;

use App\Models\CompanySetting;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppAuthService
{
    /**
     * Sanitiza e padroniza o telefone para o formato DDI + DDD + Número.
     */
    public function formatPhoneNumber(string $phone): string
    {
        $cleanPhone = preg_replace('/\D/', '', $phone);
        if (strlen($cleanPhone) === 10 || strlen($cleanPhone) === 11) {
            $cleanPhone = '55' . $cleanPhone;
        }
        return $cleanPhone;
    }

    /**
     * Valida permissão do tenant, aplica rate limit e dispara o OTP oficial pela API da Meta.
     *
     * @throws Exception
     */
    public function sendOtp(string $rawPhone): void
    {
        $setting = CompanySetting::getCached();
        $methods = $setting?->customer_auth_methods;

        if (is_string($methods)) {
            $methods = json_decode($methods, true) ?: [];
        }

        if (!is_array($methods) || !in_array('whatsapp', $methods, true)) {
            throw new Exception('A autenticação via WhatsApp está desativada no momento.', 403);
        }

        $phone = $this->formatPhoneNumber($rawPhone);
        $throttleKey = "whatsapp_otp_throttle_{$phone}";

        if (Cache::has($throttleKey)) {
            throw new Exception('Aguarde 60 segundos antes de solicitar um novo código.', 429);
        }

        $otp = (string) random_int(100000, 999999);
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $version = config('services.whatsapp.version', 'v21.0');
        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => [
                'name'     => 'auth_otp_code',
                'language' => ['code' => 'pt_BR'],
                'components' => [
                    [
                        'type'       => 'body',
                        'parameters' => [['type' => 'text', 'text' => $otp]]
                    ],
                    [
                        'type'       => 'button',
                        'sub_type'   => 'url',
                        'index'      => '0',
                        'parameters' => [['type' => 'text', 'text' => $otp]]
                    ]
                ]
            ]
        ];

        $response = Http::withToken($token)->timeout(10)->post($url, $payload);

        if ($response->failed()) {
            Log::error('Erro ao enviar WhatsApp OTP:', [
                'phone'    => $phone,
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);
            throw new Exception('Falha ao entregar o código via WhatsApp.', 502);
        }

        Cache::put("whatsapp_otp_{$phone}", $otp, now()->addMinutes(10));
        Cache::put($throttleKey, true, now()->addSeconds(60));
    }

    /**
     * Valida o código inserido pelo cliente.
     */
    public function verifyOtp(string $rawPhone, string $code): bool
    {
        $phone = $this->formatPhoneNumber($rawPhone);
        $cachedOtp = Cache::get("whatsapp_otp_{$phone}");

        if (!$cachedOtp || !hash_equals((string) $cachedOtp, $code)) {
            return false;
        }

        Cache::forget("whatsapp_otp_{$phone}");
        return true;
    }
}
