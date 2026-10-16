<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Validação do Webhook (GET) e Recepção de Eventos (POST).
     */
    public function handle(Request $request)
    {
        // 1. Handshake de Verificação da Meta (GET)
        if ($request->isMethod('get')) {
            $verifyToken = env('WHATSAPP_WEBHOOK_VERIFY_TOKEN');

            // O PHP converte pontos em query strings para underlines (hub.mode vira hub_mode)
            $mode = $request->query('hub_mode', $request->query('hub.mode'));
            $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
            $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

            if ($mode === 'subscribe' && $token === $verifyToken) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }

            return response('Token inválido', 403);
        }

        // 2. Recepção de Status / Mensagens (POST)
        if ($request->isMethod('post')) {
            $payload = $request->all();

            // Log para debug durante homologação
            Log::info('WhatsApp Webhook Recebido:', $payload);

            return response()->json(['status' => 'EVENT_RECEIVED'], 200);
        }

        return response('Método não permitido', 405);
    }
}
