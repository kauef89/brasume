<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Financial\Gateways\SicrediPixGateway;
use App\Models\CompanySetting;
use App\Models\Tenant;

class SetupSicrediWebhook extends Command
{
    protected $signature = 'sicredi:webhook {tenant_id? : O ID do tenant (ex: nativa)} {--url= : Forçar uma URL base específica (ex: https://nativa.brasume.com.br)}';

    protected $description = 'Configura a URL do Webhook do Sicredi automaticamente.';

    public function handle()
    {
        $tenantIdParam = $this->argument('tenant_id');

        if ($tenantIdParam) {
            $tenant = Tenant::find($tenantIdParam);
            if ($tenant) {
                tenancy()->initialize($tenant);
            } else {
                $this->error("Tenant '{$tenantIdParam}' não encontrado no banco de dados.");
                return;
            }
        }

        $tenant = function_exists('tenant') ? tenant() : null;

        if (!$tenant) {
            $this->error("❌ O comando falhou porque tentou rodar no banco Central.");
            $this->line("👉 Informe o ID do tenant no comando. Exemplo:");
            $this->info("php artisan sicredi:webhook nativa");
            return;
        }

        $tenantId = $tenant->id;
        $this->info("Iniciando configuração do Webhook para o Tenant: {$tenantId}");

        $settings = CompanySetting::getCached();

        if (!$settings || empty($settings->sicredi_chave_pix)) {
            $this->error("A chave PIX do Sicredi não está configurada no painel deste tenant.");
            return;
        }

        $gateway = app(SicrediPixGateway::class);

        // 🚀 CORREÇÃO: Espelha exatamente a validação do SicrediPixGateway::validateWebhookToken
        if (!empty($settings->sicredi_webhook_token)) {
            $securityToken = $settings->sicredi_webhook_token;
        } else {
            $securityToken = md5(($settings->sicredi_client_id ?? '') . ($settings->sicredi_client_secret ?? ''));
        }

        $baseUrl = $this->option('url');

        if (!$baseUrl) {
            if (method_exists($tenant, 'domains') && $tenant->domains->count() > 0) {
                $domain = $tenant->domains->first()->domain;
                $baseUrl = "https://{$domain}";
            } else {
                $baseUrl = "https://{$tenantId}.brasume.com.br";
            }
        }

        $baseUrl = rtrim($baseUrl, '/');
        $webhookUrl = "{$baseUrl}/api/webhooks/sicredi/pix?token={$securityToken}";

        $this->info("Enviando URL para o Sicredi: {$webhookUrl}");

        try {
            $gateway->registerWebhook($webhookUrl);
            $this->info('✅ Webhook registrado com sucesso! O Sicredi já sabe para onde encaminhar as notificações.');
        } catch (\Exception $e) {
            $this->error('❌ Falha: ' . $e->getMessage());
        }
    }
}
