<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        // Este evento dispara automaticamente LOGO APÓS um novo tenant ser criado no banco
        static::created(function ($tenant) {
            
            // Pega o ID da lanchonete recém-criada (ex: 'burguer')
            $tenantId = $tenant->id; 
            
            $caminhos = [
                storage_path("tenants/{$tenantId}/framework/cache"),
                storage_path("tenants/{$tenantId}/framework/views"),
                storage_path("tenants/{$tenantId}/framework/sessions"),
                storage_path("tenants/{$tenantId}/app/livewire-tmp"),
                // Pode adicionar a pasta de certificados aqui também, se quiser!
                storage_path("tenants/{$tenantId}/app/certificates"), 
            ];

            foreach ($caminhos as $caminho) {
                if (!file_exists($caminho)) {
                    // Cria a pasta, dá a permissão 775 e true (para criar as subpastas recursivamente)
                    mkdir($caminho, 0775, true);
                }
            }
        });
    }
}