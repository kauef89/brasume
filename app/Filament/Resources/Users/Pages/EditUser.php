<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Employees\EmployeeResource; // 🚀 Certifique-se de importar o seu EmployeeResource
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 🚀 BOTÃO DINÂMICO PARA NAVEGAR AO COLABORADOR
            Action::make('editEmployee')
                ->label('Editar Colaborador')
                ->icon('heroicon-o-identification')
                ->color('info')
                ->visible(fn () => filled($this->getRecord()->employee_id))
                ->url(fn () => EmployeeResource::getUrl('edit', ['record' => $this->getRecord()->employee_id])),

            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
