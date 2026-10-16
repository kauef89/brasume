<?php

namespace App\Filament\Resources\ShoppingListItems\Pages;

use App\Filament\Resources\ShoppingListItems\ShoppingListItemResource;
use App\Services\SupplyChain\ProcurementService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListShoppingListItems extends ListRecords
{
    protected static string $resource = ShoppingListItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('scanStock')
                ->label('Sugerir Compras (Varredura MRP)')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Verificar Estoque Mínimo e Sugerir Compras')
                ->modalDescription('O sistema analisará todo o estoque de insumos, materiais e produtos de revenda, comparando saldo atual com o estoque mínimo para gerar a lista de pedidos.')
                ->action(function (ProcurementService $procurementService) {
                    $count = $procurementService->generateAutomatedShoppingList(true);

                    Notification::make()
                        ->success()
                        ->title('Varredura Concluída!')
                        ->body("Foram avaliadas as necessidades do estoque: {$count} itens estão na lista de reposição.")
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('Adicionar Item Manual'),
        ];
    }
}
