<?php

namespace App\Filament\Resources\Supplies\Pages;

use App\Filament\Resources\Supplies\SupplyResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewSupply extends ViewRecord
{
    protected static string $resource = SupplyResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->record->name;
    }
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make("duplicate")
                ->label("Duplicar Material")
                ->color("gray")
                ->icon("heroicon-o-document-duplicate")
                ->requiresConfirmation()
                ->action(function () {
                    $newSupply = $this->record->replicate();
                    $newSupply->name = $newSupply->name . " (Cópia)";
                    $newSupply->save();

                    Notification::make()
                        ->title("Material Duplicado!")
                        ->success()
                        ->send();

                    return redirect(
                        SupplyResource::getUrl("view", [
                            "record" => $newSupply->id,
                        ]),
                    );
                }),
        ];
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
