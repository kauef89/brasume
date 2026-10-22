<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

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
        return "Detalhes do Produto";
    }

    // Devolvemos os botões padrão ao rodapé do formulário por segurança
    protected function getFormActions(): array
    {
        return [$this->getSaveFormAction(), $this->getCancelFormAction()];
    }

    protected function getHeaderActions(): array
    {
        return [
            // 🚀 O NOVO BOTÃO DE SALVAR NO CABEÇALHO (À PROVA DE FALHAS)
            Actions\Action::make("save_header")
                ->label("Salvar Alterações")
                ->action("save") // Chama a função "save" do backend diretamente!
                ->color("primary"),

            Actions\ReplicateAction::make()
                ->label("Duplicar")
                ->color("gray")
                ->icon("heroicon-o-document-duplicate")
                ->excludeAttributes(["sku", "barcode"]),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\Products\Widgets\ProductMarginWidget::class,
            \App\Filament\Resources\Products\Widgets\ProductCostAnalysisWidget::class,
        ];
    }
}
