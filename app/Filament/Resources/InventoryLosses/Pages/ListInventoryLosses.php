<?php

namespace App\Filament\Resources\InventoryLosses\Pages;

use App\Filament\Resources\InventoryLosses\InventoryLossResource;
use App\Models\InventoryLoss;
use App\Models\StorageLocation;
use App\Services\Catalog\InventoryService;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInventoryLosses extends ListRecords
{
    protected static string $resource = InventoryLossResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('auditBalance')
                ->label('Balanço Rápido (Modo Inventário)')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->modalHeading('Conferência de Inventário por Local')
                ->modalDescription('Selecione o local de armazenamento para comparar as quantidades contadas com o saldo do sistema. As divergências negativas serão lançadas como perdas de inventário.')
                ->form([
                    Components\Select::make('storage_location_id')
                        ->label('Local a Conferir')
                        ->options(fn () => StorageLocation::pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->live(),

                    Components\Placeholder::make('info')
                        ->content('Após selecionar o local, insira os itens e o saldo contado para atualizar a casa e gerar o relatório de auditoria.'),
                ])
                ->action(function (array $data) {
                    Notification::make()
                        ->info()
                        ->title('Local Selecionado')
                        ->body('Utilize o painel operacional para contagem detalhada com scanner ou biometria.')
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('Lançar Descarte Manual'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todas as Perdas')
                ->badge(fn () => InventoryLoss::count()),

            'operacional' => Tab::make('Erros Operacionais')
                ->badge(fn () => InventoryLoss::where('loss_type', InventoryLoss::TYPE_OPERATIONAL)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('loss_type', InventoryLoss::TYPE_OPERATIONAL)),

            'balanco' => Tab::make('Furos de Inventário')
                ->badge(fn () => InventoryLoss::where('loss_type', InventoryLoss::TYPE_INVENTORY_AUDIT)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('loss_type', InventoryLoss::TYPE_INVENTORY_AUDIT)),

            'vencidos' => Tab::make('Vencidos & Deteriorados')
                ->badge(fn () => InventoryLoss::whereIn('loss_type', [InventoryLoss::TYPE_EXPIRED, InventoryLoss::TYPE_SPOILAGE])->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('loss_type', [InventoryLoss::TYPE_EXPIRED, InventoryLoss::TYPE_SPOILAGE])),
        ];
    }
}
