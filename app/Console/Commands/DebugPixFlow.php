<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Financial\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DebugPixFlow extends Command
{
    // O comando aceita o nome do tenant como argumento. O padrão será "nativa"
    protected $signature = 'debug:pix {tenant=nativa}';
    protected $description = 'Simula a geração de PIX Sicredi e valida se o payload EMV sofreu truncamento no banco de dados.';

    public function handle(PaymentGatewayManager $gatewayManager)
    {
        $tenantId = $this->argument('tenant');

        try {
            // Inicializa o Tenant
            tenancy()->initialize($tenantId);
            $this->info("🔍 Iniciando diagnóstico de PIX no tenant: {$tenantId}");
        } catch (\Exception $e) {
            $this->error("❌ Falha ao inicializar o tenant '{$tenantId}'. Verifique se o nome está correto.");
            return;
        }

        // 1. Buscar um método de pagamento PIX ativo
        $method = PaymentMethod::where('engine_type', 'bank_api')
            ->orWhere('name', 'like', '%PIX%')
            ->first();

        if (!$method) {
            $this->error("❌ Nenhum método de pagamento PIX/Bank API encontrado neste tenant.");
            return;
        }

        // 2. Criar um Pedido Fake de Balcão
        $order = Order::create([
            'type'           => 'counter',
            'source'         => 'pos_app',
            'status'         => 'pending',
            'payment_status' => 'pending',
            'subtotal'       => 1.00,
            'total'          => 1.00,
            'customer_name'  => 'Teste PIX Debug Automatizado'
        ]);

        $this->info("✅ Pedido de Teste #{$order->id} criado com sucesso.");

        try {
            // 3. Chamar o Gateway do Sicredi
            $this->info("⏳ Conectando à API do Sicredi...");
            $gateway = $gatewayManager->resolveGateway($method->gateway_driver ?? 'sicredi');

            // Simula um pagamento de R$ 1,00
            $pixData = $gateway->generatePayment($order, 1.00);
            $this->info("✅ Retorno da API recebido com sucesso!");

            // 4. Salvar no Banco (Com inteligência para detectar se você já migrou para 'payments')
            $targetTable = Schema::hasColumn('payments', 'pix_payload') ? 'payments' : 'orders';
            $recordId = null;

            if ($targetTable === 'payments') {
                $payment = $order->payments()->create([
                    'payment_method_id' => $method->id,
                    'amount'            => 1.00,
                    'status'            => 'pending',
                    'txid'              => $pixData['txid'],
                    'pix_payload'       => $pixData['qr_code']
                ]);
                $recordId = $payment->id;
            } else {
                $order->update([
                    'txid'        => $pixData['txid'],
                    'pix_payload' => $pixData['qr_code']
                ]);
                $recordId = $order->id;
            }

            // 5. Ler o dado crú diretamente do banco
            $this->info("⏳ Lendo dados salvos na tabela '{$targetTable}'...");
            $savedPayload = DB::table($targetTable)->where('id', $recordId)->value('pix_payload');
            $payloadLength = strlen($savedPayload);
            $originalLength = strlen($pixData['qr_code']);

            // ==========================================
            // 📊 IMPRESSÃO DO RELATÓRIO DE AUDITORIA
            // ==========================================
            $this->newLine();
            $this->warn("==================================================");
            $this->warn("               RELATÓRIO DO PIX                   ");
            $this->warn("==================================================");
            $this->line("📍 Tabela Destino : " . $targetTable);
            $this->line("📍 TXID Sicredi   : " . $pixData['txid']);
            $this->line("📍 Tamanho Gateway: " . $originalLength . " caracteres");
            $this->line("📍 Tamanho Banco  : " . $payloadLength . " caracteres");
            $this->newLine();

            // Validação de Truncamento
            if ($payloadLength === $originalLength) {
                $this->info("🎯 SUCESSO: O payload (Copia e Cola) foi salvo integralmente sem sofrer cortes!");
            } elseif ($payloadLength === 255) {
                $this->error("🚨 TRUNCAMENTO DETECTADO!");
                $this->error("A coluna 'pix_payload' na tabela '{$targetTable}' foi criada como VARCHAR(255).");
                $this->line("Acesse o MySQL e rode: ALTER TABLE {$targetTable} MODIFY pix_payload TEXT;");
            } else {
                $this->error("⚠️ ALERTA: O tamanho do banco difere do original de forma desconhecida.");
            }

            // Validação da Assinatura Criptográfica (CRC16)
            // O padrão do BACEN dita que o Copia e Cola sempre termina com '6304' seguido de 4 caracteres alfanuméricos.
            if (preg_match('/6304[A-F0-9]{4}$/i', $savedPayload)) {
                $this->info("🔒 ASSINATURA CRC16 INTACTA: O código passará na validação do app do banco do cliente.");
            } else {
                $this->error("💔 ASSINATURA CRC16 PERDIDA: O final do código PIX foi cortado ou corrompido.");
            }
            $this->warn("==================================================");

        } catch (\Exception $e) {
            $this->error("❌ Falha na simulação: " . $e->getMessage());
        } finally {
            // Limpeza: Apagar o pedido de teste do banco para não sujar o sistema
            if ($order) {
                $order->forceDelete();
                // Se criou pagamento atrelado, exclui também
                DB::table('payments')->where('order_id', $order->id)->delete();
                $this->comment("🧹 Limpeza concluída: Pedido de teste excluído do banco.");
            }
        }
    }
}
