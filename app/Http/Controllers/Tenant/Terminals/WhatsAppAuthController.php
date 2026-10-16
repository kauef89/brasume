<?php

namespace App\Http\Controllers\Tenant\Terminals;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\CompanySetting;

class WhatsAppAuthController extends Controller
{
    /**
     * Gera e envia o código OTP usando o template oficial auth_otp_code.
     */
    public function sendOtp(Request $request)
    {
        // 🛡️ TRAVA DE SEGURANÇA: Bloqueia requisições forçadas se desativado no painel
        $setting = CompanySetting::getCached();
        $methods = $setting?->customer_auth_methods;

        if (is_string($methods)) {
            $methods = json_decode($methods, true) ?: [];
        }

        if (!is_array($methods) || !in_array('whatsapp', $methods, true)) {
            return response()->json([
                'message' => 'A autenticação via WhatsApp está desativada no momento.'
            ], 403);
        }

        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        // 1. Sanitização do telefone: remove tudo que não for número
        $phone = preg_replace('/\D/', '', $request->input('phone'));

        // Adiciona DDI 55 caso o cliente tenha digitado apenas DDD + número
        if (strlen($phone) === 10 || strlen($phone) === 11) {
            $phone = '55' . $phone;
        }

        // 2. Proteção contra flood (Rate Limit: 1 envio a cada 60s por número)
        $throttleKey = "whatsapp_otp_throttle_{$phone}";
        if (Cache::has($throttleKey)) {
            return response()->json([
                'message' => 'Aguarde 60 segundos antes de solicitar um novo código.'
            ], 429);
        }

        // 3. Geração do código OTP criptograficamente seguro (6 dígitos)
        $otp = (string) random_int(100000, 999999);

        // 4. Parâmetros da API Meta
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $version = config('services.whatsapp.version', 'v21.0');
        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        // 5. Disparo do Template auth_otp_code com botão de Copiar Código
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => [
                'name'     => 'auth_otp_code',
                'language' => [
                    'code' => 'pt_BR'
                ],
                'components' => [
                    [
                        'type'       => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $otp
                            ]
                        ]
                    ],
                    [
                        'type'       => 'button',
                        'sub_type'   => 'url',
                        'index'      => '0',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $otp
                            ]
                        ]
                    ]
                ]
            ]
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::error('Erro ao enviar WhatsApp OTP:', [
                    'phone'    => $phone,
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ]);

                return response()->json([
                    'message' => 'Falha ao entregar o código via WhatsApp. Tente novamente mais tarde.'
                ], 502);
            }

            // 6. Armazena no Cache com validade de 10 minutos
            Cache::put("whatsapp_otp_{$phone}", $otp, now()->addMinutes(10));
            Cache::put($throttleKey, true, now()->addSeconds(60));

            return response()->json([
                'success' => true,
                'message' => 'Código de verificação enviado com sucesso.'
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Exceção ao disparar WhatsApp OTP: ' . $e->getMessage());

            return response()->json([
                'message' => 'Erro interno de comunicação com a Meta.'
            ], 500);
        }
    }

    /**
     * Valida o código inserido pelo cliente.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'code'  => ['required', 'string', 'size:6'],
        ]);

        $phone = preg_replace('/\D/', '', $request->input('phone'));
        if (strlen($phone) === 10 || strlen($phone) === 11) {
            $phone = '55' . $phone;
        }

        $cachedOtp = Cache::get("whatsapp_otp_{$phone}");

        if (!$cachedOtp || !hash_equals((string) $cachedOtp, (string) $request->input('code'))) {
            return response()->json([
                'message' => 'Código inválido ou expirado.'
            ], 422);
        }

        // Código validado: expira o cache para evitar reutilização
        Cache::forget("whatsapp_otp_{$phone}");

        // TODO: Localizar ou criar o registro do Customer correspondente ao tenant atual
        // e emitir o token Sanctum ou autenticar a sessão do cliente.

        return response()->json([
            'success' => true,
            'message' => 'Telefone autenticado com sucesso.'
        ], 200);
    }
}
