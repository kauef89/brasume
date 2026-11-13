<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\EditAction;

class ViewEmployee extends ViewRecord
{

    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->record->name;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Ficha Cadastral';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-identification';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->color('primary')
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}