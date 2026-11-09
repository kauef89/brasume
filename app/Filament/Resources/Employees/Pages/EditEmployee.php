<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Traits\RedirectsToIndex;
use App\Filament\Traits\TeleportsTabs;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    use RedirectsToIndex;
    use TeleportsTabs;

    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return "Editar {$this->record->name}";
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Editar Dados';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-pencil-square';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}