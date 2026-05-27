<?php

namespace App\Filament\Resources\Ingredients\Pages;

use App\Filament\Resources\Ingredients\IngredientResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIngredient extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = IngredientResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->record->name;
    }

    // 🚀 AQUI ACONTECE A MÁGICA: Manda os botões pro cabeçalho da página!
    protected function getHeaderActions(): array
    {
        return [
            // O botão de Salvar original, reconfigurado para rodar o formulário daqui de cima
            $this->getSaveFormAction()->formId("form"),

            // O seu botão de Deletar
            DeleteAction::make(),
        ];
    }

    // 🚀 Mata os botões que ficavam escondidos/perdidos no meio da tela e das abas
    protected function getFormActions(): array
    {
        return [];
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
