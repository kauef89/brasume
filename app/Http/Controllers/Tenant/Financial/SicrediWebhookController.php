<?php

namespace App\Http\Controllers\Tenant\Financial;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Services\Orders\OrderPaymentService;
use App\Services\Financial\Gateways\SicrediPixGateway;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Payment;

class SicrediWebhookController extends Controller
{
    public function handlePix(Request $request, OrderPaymentService $paymentService, SicrediPixGateway $gateway)
    {
        $payload = $request->all();

        Log::info('📍 [WEBHOOK] Notificação Pix recebida do Sicredi.', [
            'ip'        => $request->ip(),
            'query'     => $request->query(),
            'total_pix' => isset($payload['pix']) && is_array($payload['pix']) ? count($payload['pix']) : 0,
        ]);

        if (!$gateway->validateWebhookToken($request->query('token', ''))) {
            Log::warning('🚨 [WEBHOOK] Falha na validação do Token de segurança!', [
                'ip'             => $request->ip(),
                'token_recebido' => $request->query('token'),
            ]);
            return response()->json(['message' => 'Acesso Negado'], 403);
        }

        if (!isset($payload['pix']) || !is_array($payload['pix'])) {
            Log::warning('🚨 [WEBHOOK] Payload recebido não contém o array "pix". Ignorando.');
            return response()->json(['message' => 'Payload ignorado'], 400);
        }

        $settings = CompanySetting::getCached() ?? CompanySetting::first();
        $isRelayEnabled = (bool) ($settings->sicredi_share_pix_key ?? false);
        $relayUrl = $settings->sicredi_relay_url ?? env('SICREDI_RELAY_WEBHOOK_URL');

        $relayPix = [];

        foreach ($payload['pix'] as $pix) {
            $txid = $pix['txid'] ?? null;

            if (empty($txid)) {
                Log::warning('🚨 [WEBHOOK] Transação Pix recebida sem TXID.', $pix);
                continue;
            }

            // 🔍 Verifica se o TXID pertence a um pedido deste restaurante
            $isBrasumeTxid = Order::where('txid', $txid)
                ->orWhere('payment_intent_methods', 'like', "%\"txid\":\"{$txid}\"%")
                ->exists()
                || Payment::where('txid', $txid)->exists();

            if ($isBrasumeTxid) {
                // 🍔 Transação interna (Cardápio / PDV / Garçom)
                Log::info("📍 [WEBHOOK BRASUME] TXID identificado no sistema: {$txid}");

                $lock = Cache::lock("webhook_pix_processing_{$txid}", 300);

                if ($lock->get()) {
                    try {
                        $paymentService->processWebhookPayment($txid, (float) $pix['valor'], $pix['endToEndId'] ?? null);
                        Log::info("✅ [WEBHOOK BRASUME] TXID {$txid} liquidado com sucesso!");
                    } catch (\Throwable $e) {
                        Log::error("🚨 [WEBHOOK BRASUME ERRO] Falha ao processar TXID {$txid}: " . $e->getMessage());
                    } finally {
                        $lock->release();
                    }
                } else {
                    Log::warning("⚠️ [WEBHOOK BRASUME] TXID {$txid} em processamento concorrente.");
                }
            } else {
                // 💳 Transação externa (Maquininha APOS / SiTef)
                if ($isRelayEnabled && !empty($relayUrl)) {
                    Log::info("🔄 [WEBHOOK RELAY] TXID {$txid} marcado para repasse à adquirente/SiTef.");
                    $relayPix[] = $pix;
                } else {
                    Log::info("ℹ️ [WEBHOOK] TXID {$txid} não pertence ao ERP e o Relay está inativo. Ignorado.");
                }
            }
        }

        // 🚀 Executa o Relay se houver transações externas e o recurso estiver ativado
        if (!empty($relayPix) && !empty($relayUrl)) {
            Log::info('🔄 [WEBHOOK RELAY] Encaminhando ' . count($relayPix) . ' transação(ões) para a Software Express...', [
                'url'   => $relayUrl,
                'txids' => array_column($relayPix, 'txid'),
            ]);

            try {
                $relayPayload = $payload;
                $relayPayload['pix'] = $relayPix;

                $response = Http::timeout(8)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'User-Agent'   => 'Brasume-Pix-Relay/1.0',
                    ])
                    ->post($relayUrl, $relayPayload);

                Log::info('🔄 [WEBHOOK RELAY] Resposta da Software Express:', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 300),
                ]);
            } catch (\Throwable $e) {
                Log::error('🚨 [WEBHOOK RELAY ERRO] Falha ao retransmitir para Software Express: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Webhook processado com sucesso'], 200);
    }
}
