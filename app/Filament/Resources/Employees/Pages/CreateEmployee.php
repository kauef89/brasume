<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Users\UserResource; // 🚀 IMPORTADO PARA CONSTRUIR A URL
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = EmployeeResource::class;

    // 🚀 MÁGICA DE ONBOARDING: Redireciona o operador diretamente para criar a conta de acesso!
    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('create', [
            'employee_id' => $this->record->id,
        ]);
    }
}
