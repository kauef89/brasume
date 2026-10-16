<?php

namespace App\Filament\Central\Resources\Tenants\Pages;

use App\Filament\Central\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = TenantResource::class;

    protected function afterCreate(): void
    {
        $tenant = $this->record;

        // 🚀 RECUPERA O CAMPO FANTASMA DO FORMULÁRIO (E não o hardcode do brasume)
        $customDomain = $this->data['custom_domain'] ?? null;

        if ($customDomain) {
            $tenant->domains()->create([
                'domain' => strtolower(trim($customDomain))
            ]);
        }
    }
}
