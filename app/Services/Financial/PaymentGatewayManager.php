<?php

namespace App\Services\Financial;

use App\Models\CompanySetting;
use Exception;
use Illuminate\Support\HtmlString;
use App\Services\Financial\Gateways\PaymentGatewayInterface;
use App\Services\Financial\Gateways\SicrediPixGateway;

class PaymentGatewayManager
{
    /**
     * Retorna a lista de integrações para o Filament
     */
    public static function getAvailableGateways(): array
    {
        return [
            'sicredi_pix' => 'Sicredi: PIX Dinâmico',
            'mercado_pago_pix' => 'Mercado Pago: PIX (Em Breve)',
        ];
    }

    public function resolveGateway(string $gatewayDriver): PaymentGatewayInterface
    {
        return match ($gatewayDriver) {
            'sicredi_pix' => new SicrediPixGateway(),
            default => throw new Exception("Gateway não suportado para o driver: {$gatewayDriver}"),
        };
    }

    /**
     * 🚀 LÓGICA DE UX EXTRAÍDA: Analisa e devolve o HTML do Status para o Form!
     */
    public static function getCredentialStatusHtml(?string $driver): HtmlString
    {
        if (!$driver) return new HtmlString('-');

        $settings = CompanySetting::getCached();

        // REGRAS DO SICREDI
        if ($driver === 'sicredi_pix') {
            if (!$settings->sicredi_is_active) {
                return new HtmlString('<span class="text-danger-600 font-medium">❌ Integração Desativada:</span> O Sicredi está desligado nas Configurações.');
            }
            if (empty($settings->sicredi_client_id) || empty($settings->sicredi_cert_path) || empty($settings->sicredi_key_path)) {
                return new HtmlString('<span class="text-warning-600 font-medium">⚠️ Faltam Credenciais:</span> Preencha o Client ID, Secret e os Certificados nas Configurações.');
            }

            try {
                // Tenta o Handshake!
                $gateway = new SicrediPixGateway();
                if ($gateway->testConnection()) {
                    return new HtmlString('<span class="text-success-600 font-medium">Online:</span> Comunicação mTLS com o Sicredi estabelecida com sucesso!');
                }
            } catch (\Exception $e) {
                return new HtmlString('<span class="text-danger-600 font-medium">❌ Erro de Conexão:</span> ' . $e->getMessage());
            }
        }

        // REGRAS DO MERCADO PAGO (Exemplo Futuro)
        if (str_starts_with($driver, 'mercado_pago')) {
            if (!$settings->mp_is_active) {
                return new HtmlString('<span class="text-danger-600 font-medium">❌ Integração Desativada:</span> O Mercado Pago está desligado nas Configurações.');
            }
            return new HtmlString('<span class="text-warning-600 font-medium">⏳ Em Desenvolvimento:</span> Disponível em breve.');
        }

        return new HtmlString('-');
    }
}
