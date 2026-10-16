<?php

namespace App\Filament\Resources\ProductModifiers\Pages;

use App\Filament\Resources\ProductModifiers\ProductModifierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Select;
use App\Models\Category;
use App\Models\Product;
use Filament\Notifications\Notification;

class EditModifierGroup extends EditRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = ProductModifierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 🚀 O BOTÃO DE BULK ASSIGNMENT AGORA TOTALMENTE ELOQUENT!
            Actions\Action::make('bulk_assign')
                ->label('Vincular a Categorias')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->modalHeading('Vincular a todos os produtos de categorias selecionadas')
                ->modalDescription('Isto fará com que este grupo de modificadores seja atrelado individualmente a todos os produtos das categorias que você selecionar abaixo.')
                ->form([
                    Select::make('category_ids')
                        ->label('Escolha as Categorias')
                        ->options(Category::where('type', 'external')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                ])
                ->action(function (array $data) {
                    // 1. Busca os IDs de todos os produtos pertencentes às categorias escolhidas
                    $productIds = Product::whereIn('category_id', $data['category_ids'])->pluck('id')->toArray();

                    if (empty($productIds)) {
                        Notification::make()
                            ->warning()
                            ->title('Nenhum produto afetado')
                            ->body('As categorias selecionadas não possuem produtos cadastrados no momento.')
                            ->send();
                        return;
                    }

                    // 2. Prepara a carga de dados padrão da tabela Pivot (campo excluded_options vazio)
                    $pivotData = [];
                    foreach ($productIds as $pid) {
                        $pivotData[$pid] = ['excluded_options' => json_encode([])];
                    }

                    // 🚀 3. A MÁGICA REATIVA DO ELOQUENT:
                    // Vincula em lote ignorando quem já estava associado (preserva customizações locais).
                    // O método retorna um array discriminando o que foi feito.
                    $syncResult = $this->getRecord()->products()->syncWithoutDetaching($pivotData);

                    // Descobre quantos produtos ganharam o vínculo inédito nesta ação
                    $count = count($syncResult['attached'] ?? []);

                    Notification::make()
                        ->success()
                        ->title('Associação em Massa Concluída!')
                        ->body("O grupo foi vinculado a {$count} novos produtos com sucesso.")
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
