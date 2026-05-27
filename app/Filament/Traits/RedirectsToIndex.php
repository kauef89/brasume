<?php

namespace App\Filament\Traits;

trait RedirectsToIndex
{
    protected function getRedirectUrl(): string
    {
        $resource = $this->getResource();

        // 1. Tenta redirecionar para a página de View (Visualização)
        if ($resource::hasPage('view')) {
            return $resource::getUrl('view', ['record' => $this->getRecord()]);
        }

        // 2. Se o recurso não tiver View, tenta ir para a página de Edit (Edição)
        if ($resource::hasPage('edit')) {
            return $resource::getUrl('edit', ['record' => $this->getRecord()]);
        }

        // 3. Fallback de segurança: volta para a Tabela (Index) se não tiver nem View nem Edit
        return $resource::getUrl('index');
    }
}
