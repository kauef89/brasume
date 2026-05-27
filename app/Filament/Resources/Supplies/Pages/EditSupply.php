<?php

namespace App\Filament\Resources\Supplies\Pages;

use App\Filament\Resources\Supplies\SupplyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSupply extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = SupplyResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->record->name;
    }

    protected static ?string $recordTitleAttribute = "name";

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return "Dados do Material";
    }
}
