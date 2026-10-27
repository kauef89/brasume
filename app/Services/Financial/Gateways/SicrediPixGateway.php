<?php

namespace App\Services\Financial\Gateways;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class SicrediPixGateway implements PaymentGatewayInterface
{
    private $settings;
    private $baseUrl;

    public function __construct()
    {
        $this->settings = CompanySetting::getCached();
        $this->baseUrl = 'https://api-pix.sicredi.com.br';
    }

    private function getHttpOptions(): array
    {
        $certRelativePath = $this->settings->sicredi_cert_path;
        $keyRelativePath  = $this->settings->sicredi_key_path;

        if (empty($certRelativePath) || empty($keyRelativePath)) {
            throw new Exception("Caminhos vazios! Por favor, anexe os certificados no painel antes de testar.");
        }

        $certPath = \Illuminate\Support\Facades\Storage::disk('local')->path($certRelativePath);
        $keyPath  = \Illuminate\Support\Facades\Storage::disk('local')->path($keyRelativePath);

        if (!file_exists($certPath)) {
            throw new Exception("Certificado mTLS não encontrado no cofre privado: {$certPath}");
        }

        if (!file_exists($keyPath)) {
            throw new Exception("Chave privada mTLS não encontrada no cofre privado: {$keyPath}");
        }

        return [
            'cert'    => $certPath,
            'ssl_key' => $keyPath,
        ];
    }

    public function obterTokenOauth(): string
    {
        $response = Http::withOptions($this->getHttpOptions())
            ->withBasicAuth($this->settings->sicredi_client_id, $this->settings->sicredi_client_secret)
            ->asForm()
            ->post("{$this->baseUrl}/oauth/token", [
                'grant_type' => 'client_credentials',
                'scope'      => 'cob.write cob.read webhook.read webhook.write'
            ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        Log::error('Erro Sicredi OAuth', ['response' => $response->body()]);
        throw new Exception("Falha no Handshake mTLS com Sicredi: " . $response->json('detail', $response->body()));
    }

    /**
     * 🚀 Higienização blindada da Chave PIX segundo regras do BACEN.
     * Evita que o app do cliente retorne "PIX Inválido".
     */
    private function sanitizePixKey(string $key): string
    {
        $key = trim($key);

        // Se for E-mail, retorna limpo
        if (filter_var($key, FILTER_VALIDATE_EMAIL)) {
            return $key;
        }

        // Se for Chave Aleatória (EVP/UUID), retorna em minúsculas
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            return strtolower($key);
        }

        // Remove tudo que não for número (para limpar máscaras de CPF, CNPJ e Celular)
        $digits = preg_replace('/[^0-9]/', '', $key);

        // Se o usuário digitou o telefone já com o +55
        if (str_starts_with($key, '+55') && (strlen($digits) === 12 || strlen($digits) === 13)) {
            return '+' . $digits;
        }

        // Se for telefone com DDD (10 ou 11 dígitos), injeta o +55 obrigatório do BACEN
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            return '+55' . $digits;
        }

        // Retorna apenas números para CPF (11) ou CNPJ (14)
        return $digits;
    }

    public function generatePayment($order, $amount): array
    {
        $token = $this->obterTokenOauth();
        $txid = $this->generateTxid();

        $chaveLimpa = $this->sanitizePixKey($this->settings->sicredi_chave_pix);

        $payload = [
            "calendario" => ["expiracao" => 3600],
            "valor" => [
                "original" => number_format($amount, 2, '.', ''),
                "modalidadeAlteracao" => 0
            ],
            "chave" => $chaveLimpa,
            "solicitacaoPagador" => "Pedido #" . $order->id,
        ];

        $response = Http::withOptions($this->getHttpOptions())
            ->withToken($token)
            ->put("{$this->baseUrl}/api/v3/cob/{$txid}", $payload);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'qr_code' => $data['pixCopiaECola'],
                'txid'    => $data['txid'],
                'status'  => 'pending'
            ];
        }

        Log::error('Erro Sicredi Gerar COB', ['payload' => $payload, 'response' => $response->body()]);
        throw new Exception("Erro ao gerar PIX Sicredi: " . $response->json('detail', $response->body()));
    }

    private function generateTxid(): string
    {
        return substr(preg_replace('/[^a-zA-Z0-9]/', '', (string) Str::uuid() . Str::random(10)), 0, 30);
    }

    public function testHandshake(): array
    {
        try {
            $token = $this->obterTokenOauth();
            return [
                'success' => true,
                'message' => 'Handshake mTLS estabelecido com sucesso!',
                'token_preview' => substr($token, 0, 15) . '...'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function generateTestPix(): array
    {
        try {
            $token = $this->obterTokenOauth();
            $txid = $this->generateTxid();

            $chaveLimpa = $this->sanitizePixKey($this->settings->sicredi_chave_pix);

            $payload = [
                "calendario" => ["expiracao" => 300],
                "valor" => ["original" => "0.01", "modalidadeAlteracao" => 0],
                "chave" => $chaveLimpa,
                "solicitacaoPagador" => "PIX TESTE DE CONECTIVIDADE",
            ];

            $response = Http::withOptions($this->getHttpOptions())
                ->withToken($token)
                ->put("{$this->baseUrl}/api/v3/cob/{$txid}", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'PIX de teste gerado!',
                    'txid' => $txid,
                    'copia_e_cola' => $response->json('pixCopiaECola')
                ];
            }

            return ['success' => false, 'message' => $response->json('detail', $response->body())];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function validateWebhookToken(string $tokenReceived): bool
    {
        if (!empty($this->settings->sicredi_webhook_token)) {
            return hash_equals($this->settings->sicredi_webhook_token, $tokenReceived);
        }

        $expectedToken = md5(($this->settings->sicredi_client_id ?? '') . ($this->settings->sicredi_client_secret ?? ''));
        return hash_equals($expectedToken, $tokenReceived);
    }

    public function testConnection(): bool
    {
        try {
            $this->obterTokenOauth();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 🚀 Alterado para buscar o TXID de forma inteligente (suporta Model Order ou Payment)
     */
    public function refundPayment($model, $amount): array
    {
        $token = $this->obterTokenOauth();

        // Extrai o txid do modelo enviado (seja ele Order ou Payment), ou busca do relacionamento se aplicável
        $txid = $model->txid ?? (method_exists($model, 'payments') ? $model->payments()->whereNotNull('txid')->latest('id')->value('txid') : null);

        if (!$txid) {
            throw new Exception("Não foi possível localizar o TXID do pagamento para realizar o estorno.");
        }

        $responseCob = Http::withOptions($this->getHttpOptions())
            ->withToken($token)
            ->get("{$this->baseUrl}/api/v3/cob/{$txid}");

        if (!$responseCob->successful() || empty($responseCob->json('pix'))) {
            throw new Exception("Não foi possível localizar o pagamento original na API do Sicredi para realizar o estorno.");
        }

        $e2eid = $responseCob->json('pix')[0]['endToEndId'];
        $idControleDevolucao = str_replace('-', '', (string) Str::uuid());

        $payload = [
            "valor" => number_format($amount, 2, '.', '')
        ];

        $responseDevolucao = Http::withOptions($this->getHttpOptions())
            ->withToken($token)
            ->put("{$this->baseUrl}/api/v2/pix/{$e2eid}/devolucao/{$idControleDevolucao}", $payload);

        if ($responseDevolucao->successful()) {
            return [
                'success' => true,
                'devolucao_id' => $idControleDevolucao,
                'status' => $responseDevolucao->json('status')
            ];
        }

        Log::error('Erro Sicredi Estorno', ['payload' => $payload, 'response' => $responseDevolucao->body()]);
        throw new Exception("Erro ao estornar PIX Sicredi: " . $responseDevolucao->json('detail', $responseDevolucao->body()));
    }

    public function registerWebhook(string $webhookUrl): array
    {
        $token = $this->obterTokenOauth();
        $chavePix = $this->sanitizePixKey($this->settings->sicredi_chave_pix);

        $response = Http::withOptions($this->getHttpOptions())
            ->withToken($token)
            ->put("{$this->baseUrl}/api/v2/webhook/{$chavePix}", [
                'webhookUrl' => $webhookUrl
            ]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Webhook registrado com sucesso!'];
        }

        Log::error('Erro Sicredi Webhook', ['response' => $response->body()]);
        throw new Exception("Erro ao registrar Webhook: " . $response->json('detail', $response->body()));
    }
}
