<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

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
        return "Detalhes do Produto"; // Nome da aba que vai conter o seu formulário original
    }

    protected function getHeaderActions(): array
    {
        return [
            // Botão de Edição
            Actions\EditAction::make(),

            // Botão de Duplicação (Migrado do formulário para aqui)
            Actions\Action::make("duplicate")
                ->label("Duplicar Produto")
                ->color("gray")
                ->icon("heroicon-o-document-duplicate")
                ->requiresConfirmation()
                ->action(function () {
                    $newProduct = $this->record->replicate();
                    $newProduct->name = $newProduct->name . " (Cópia)";
                    $newProduct->save();

                    Notification::make()
                        ->title("Produto Duplicado!")
                        ->success()
                        ->send();

                    return redirect(
                        ProductResource::getUrl("view", [
                            "record" => $newProduct->id,
                        ]),
                    );
                }),
        ];
    }
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\Products\Widgets\ProductMarginWidget::class,
        ];
    }
}
