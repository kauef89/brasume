<?php

namespace App\Filament\Central\Resources\Tenants\Pages;

use App\Filament\Central\Resources\Tenants\TenantResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex; //

    protected static string $resource = TenantResource::class; //

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(), //
        ];
    }

    // 🚀 GANCHO DE ATUALIZAÇÃO: Salva as alterações do domínio na tabela relacionada
    protected function afterSave(): void
    {
        $tenant = $this->record;
        $customDomain = $this->data['custom_domain'] ?? null;

        if ($customDomain) {
            $domainModel = $tenant->domains()->first();

            if ($domainModel) {
                // Se já existe um domínio registrado, atualiza
                $domainModel->update([
                    'domain' => strtolower(trim($customDomain)),
                ]);
            } else {
                // Caso o tenant estivesse sem domínio por algum motivo, cria um novo
                $tenant->domains()->create([
                    'domain' => strtolower(trim($customDomain)),
                ]);
            }
        }
    }
}
