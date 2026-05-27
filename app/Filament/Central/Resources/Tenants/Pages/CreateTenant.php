<?php

namespace App\Filament\Central\Resources\Tenants\Pages;

use App\Filament\Central\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = TenantResource::class;

    // A MÁGICA ACONTECE AQUI! Quando a lanchonete é criada:
    protected function afterCreate(): void
    {
        // O registo recém-criado
        $tenant = $this->record;

        // O domínio base do seu SaaS
        $baseDomain = 'brasume.com.br';

        // Anexamos o subdomínio à loja (para o middleware de rotas funcionar depois!)
        $tenant->domains()->create([
            'domain' => $tenant->id . '.' . $baseDomain,
        ]);
    }
}