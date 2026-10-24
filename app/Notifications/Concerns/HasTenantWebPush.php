<?php

namespace App\Notifications\Concerns;

use App\Models\CompanySetting;

trait HasTenantWebPush
{
    /**
     * Retorna a URL pública absoluta da Logo do Tenant priorizando o custom_domain.
     */
    protected function resolveTenantIconUrl(): string
    {
        $settings = CompanySetting::getCached();
        
        if ($settings && $settings->logo_path) {
            return $settings->getBaseUrl('/media/file?path=' . $settings->logo_path);
        }

        if ($settings) {
            return $settings->getBaseUrl('/icon-192x192.png');
        }

        return url('/icon-192x192.png');
    }

    /**
     * Retorna a URL pública absoluta da Badge monocromática do Tenant.
     */
    protected function resolveTenantBadgeUrl(): string
    {
        $settings = CompanySetting::getCached();

        if ($settings && $settings->push_badge_path) {
            return $settings->getBaseUrl('/media/file?path=' . $settings->push_badge_path);
        }

        return $this->resolveTenantIconUrl();
    }

    /**
     * Retorna a URL de destino absoluta do Tenant (ex: https://pastelarianativa.com.br/customer/orders).
     */
    protected function resolveTenantActionUrl(string $path = '/'): string
    {
        $settings = CompanySetting::getCached();

        if ($settings) {
            return $settings->getBaseUrl($path);
        }

        return url($path);
    }

    /**
     * Sintoniza o VAPID_SUBJECT do envio para o domínio próprio do tenant (RFC 8292).
     */
    protected function applyTenantVapidSubject(): void
    {
        $settings = CompanySetting::getCached();

        if ($settings && !empty($settings->custom_domain)) {
            // Usa o domínio customizado como origem RFC 8292
            config(['webpush.vapid.subject' => 'https://' . $settings->custom_domain]);
        }
    }
}