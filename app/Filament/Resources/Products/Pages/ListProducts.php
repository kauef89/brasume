<?php
namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\Products\Actions\SmartMergeAction;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Traits\TeleportsTabs;

class ListProducts extends ListRecords
{
    use TeleportsTabs;
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SmartMergeAction::make()->categoryType('external'),
            Actions\CreateAction::make(),
        ];
    }

    // 🚀 A MÁGICA DAS ABAS: Separa a listagem e ainda mostra um badge com a contagem!
    public function getTabs(): array
    {
        return [
            'ativos' => Tab::make('Catálogo Ativo')
                ->badge(fn() => static::getResource()::getEloquentQuery()->whereIn('status', ['available', 'out_of_stock'])->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['available', 'out_of_stock'])),

            'arquivados' => Tab::make('Arquivados')
                ->badge(fn() => static::getResource()::getEloquentQuery()->where('status', 'archived')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'archived')),

            'todos' => Tab::make('Todos'),
        ];
    }
}
