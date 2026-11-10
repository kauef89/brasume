<?php

namespace App\Filament\Resources\Ingredients\Pages;

use App\Filament\Resources\Ingredients\IngredientResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

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
            // 🚀 Redireciona diretamente para a página cheia de edição em vez de abrir modal
            Actions\Action::make('edit')
                ->label('Editar Insumo / Ficha')
                ->icon('heroicon-m-pencil-square')
                ->color('primary')
                ->url(fn (): string => IngredientResource::getUrl('edit', ['record' => $this->record])),

            Actions\Action::make('duplicate')
                ->label('Duplicar Insumo')
                ->color('gray')
                ->icon('heroicon-o-document-duplicate')
                ->requiresConfirmation()
                ->modalHeading('Duplicar este insumo?')
                ->modalDescription('Uma cópia exata deste insumo será criada. Você poderá editar o nome e os detalhes em seguida.')
                ->action(function () {
                    $newIngredient = $this->record->replicate();
                    $newIngredient->name = $newIngredient->name . ' (Cópia)';
                    $newIngredient->save();

                    Notification::make()
                        ->title('Insumo Duplicado com Sucesso!')
                        ->success()
                        ->send();

                    return redirect(
                        IngredientResource::getUrl('edit', [
                            'record' => $newIngredient->id,
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
        return 'Cadastro do Insumo';
    }
}
