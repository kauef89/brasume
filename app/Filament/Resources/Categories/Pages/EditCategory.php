<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Events\CatalogUpdated; // 🚀 Importado
use Illuminate\Support\Facades\Log; // 🚀 Importado

class EditCategory extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // 🚀 O GATILHO INFALÍVEL DO FILAMENT (O SEU CONTROLLER)
    protected function afterSave(): void
    {
        // $this->record é a categoria que acabou de ser editada e salva com sucesso
        $tenantId = $this->record->tenant_id ?? tenant('id') ?? 'central';

        Log::info("⚡ GATILHO FILAMENT (Edit): Avisando Tenant {$tenantId}");

        try {
            \Illuminate\Support\Facades\Cache::tags(['catalog'])->flush();
        } catch (\Exception $e) { }

        // Dispara o aviso para o Reverb (Fila) !
        broadcast(new CatalogUpdated($tenantId));
    }
}
