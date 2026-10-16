<?php

namespace App\Http\Controllers\Tenant\Financial;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\Orders\OrderPaymentService;
use App\Services\Financial\Gateways\SicrediPixGateway;

class SicrediWebhookController extends Controller
{
    public function handlePix(Request $request, OrderPaymentService $paymentService, SicrediPixGateway $gateway)
    {
        $payload = $request->all();

        // 🚀 SONDA 1: O banco chegou a bater na nossa porta?
        Log::info('📍 [SONDA 1] Webhook Sicredi Recebido. Payload:', $payload);

        if (!$gateway->validateWebhookToken($request->query('token', ''))) {
            Log::warning('🚨 [SONDA 2] Falha na validação do Token do Webhook!', [
                'ip' => $request->ip(),
                'token_recebido' => $request->query('token')
            ]);
            return response()->json(['message' => 'Acesso Negado'], 403);
        }

        Log::info('📍 [SONDA 3] Token de segurança validado com sucesso.');

        if (!isset($payload['pix']) || !is_array($payload['pix'])) {
            Log::warning('🚨 [SONDA 4] Payload recebido não contém o array "pix". Ignorando.');
            return response()->json(['message' => 'Payload ignorado'], 400);
        }

        foreach ($payload['pix'] as $pix) {
            if (empty($pix['txid'])) {
                Log::warning('🚨 [SONDA 5] Transação veio sem TXID.', $pix);
                continue;
            }

            Log::info("📍 [SONDA 6] Iniciando processamento do TXID: {$pix['txid']}");

            // Trava atômica
            $lock = Cache::lock("webhook_pix_processing_{$pix['txid']}", 300);

            if ($lock->get()) {
                Log::info("📍 [SONDA 7] Lock adquirido! Enviando para o OrderPaymentService...");
                try {
                    $paymentService->processWebhookPayment($pix['txid'], $pix['valor'], $pix['endToEndId'] ?? null);
                    Log::info("📍 [SONDA 12] Sucesso absoluto! O TXID {$pix['txid']} foi processado até o fim.");
                } catch (\Exception $e) {
                    Log::error("🚨 [SONDA ERRO] Falha ao processar TXID {$pix['txid']}: " . $e->getMessage() . " | Linha: " . $e->getLine());
                } finally {
                    $lock->release();
                }
            } else {
                Log::warning("🚨 [SONDA 7 - BLOCK] TXID {$pix['txid']} ignorado. Já está em processamento concorrente.");
            }
        }

        return response()->json(['message' => 'Webhook recebido com sucesso'], 200);
    }
}
