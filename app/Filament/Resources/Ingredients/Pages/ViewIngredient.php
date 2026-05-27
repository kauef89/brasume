<?php

namespace App\Filament\Resources\Ingredients\Pages;

use App\Filament\Resources\Ingredients\IngredientResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewIngredient extends ViewRecord
{
    protected static string $resource = IngredientResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Botão padrão para ir para a tela de edição
            Actions\EditAction::make(),

            // Ação personalizada para Duplicar o Insumo
            // Útil para quando você tem insumos muito parecidos (ex: marcas diferentes do mesmo produto)
            Actions\Action::make("duplicate")
                ->label("Duplicar Insumo")
                ->color("gray")
                ->icon("heroicon-o-document-duplicate")
                ->requiresConfirmation()
                ->modalHeading("Duplicar este insumo?")
                ->modalDescription(
                    "Uma cópia exata deste insumo será criada. Você poderá editar o nome e os detalhes em seguida.",
                )
                ->action(function () {
                    // Replica o registro atual do banco de dados
                    $newIngredient = $this->record->replicate();

                    // Adiciona um sufixo para diferenciar da original
                    $newIngredient->name = $newIngredient->name . " (Cópia)";

                    // Salva o novo registro
                    $newIngredient->save();

                    Notification::make()
                        ->title("Insumo Duplicado com Sucesso!")
                        ->success()
                        ->send();

                    // Redireciona o usuário para a visualização do novo registro criado
                    return redirect(
                        IngredientResource::getUrl("view", [
                            "record" => $newIngredient->id,
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

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return "Cadastro do Insumo";
    }
}
