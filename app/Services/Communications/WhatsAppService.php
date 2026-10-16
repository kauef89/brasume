<?php

namespace App\Services\Communications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\CompanySetting;
use Exception;

class WhatsAppService
{
    public function sendOtp(string $phone, string $code): bool
    {
        $settings = CompanySetting::getCached();

        // Pega as configurações ou usa variáveis de ambiente como fallback
        $token = $settings?->wa_provider_token ?? env('WHATSAPP_TOKEN');
        $endpoint = $settings?->wa_provider_url ?? env('WHATSAPP_ENDPOINT', 'https://graph.facebook.com/v17.0/YOUR_PHONE_ID/messages');

        if (!$token || !$endpoint) {
            Log::warning("WhatsApp API não configurada. OTP gerado: {$code} para {$phone}");
            return false; // Retorne true em ambiente de desenvolvimento se quiser testar sem enviar
        }

        // Limpa o número (apenas dígitos) e adiciona DDI se faltar
        $cleanPhone = preg_replace('/\D/', '', $phone);
        if (strlen($cleanPhone) <= 11) {
            $cleanPhone = '55' . $cleanPhone;
        }

        try {
            $response = Http::withToken($token)->post($endpoint, [
                'messaging_product' => 'whatsapp',
                'to' => $cleanPhone,
                'type' => 'template',
                'template' => [
                    'name' => 'auth_otp_code', // Nome do template aprovado na Meta
                    'language' => ['code' => 'pt_BR'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $code]
                            ]
                        ],
                        [
                            'type' => 'button',
                            'sub_type' => 'url',
                            'index' => '0',
                            'parameters' => [
                                ['type' => 'text', 'text' => $code] // Preenche a variável do botão de copiar código
                            ]
                        ]
                    ]
                ]
            ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error("Erro ao enviar OTP WA: " . $e->getMessage());
            return false;
        }
    }
}
