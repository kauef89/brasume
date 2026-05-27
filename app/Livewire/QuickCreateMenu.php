<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Concerns\InteractsWithForms;

// Nossos formulários
use App\Filament\Resources\Ingredients\IngredientResource;
use App\Filament\Resources\Supplies\SupplyResource;
use App\Filament\Resources\Products\ProductResource;

class QuickCreateMenu extends Component implements HasForms, HasActions
{
    use InteractsWithActions;
    use InteractsWithForms;

    // 1. Ação do Insumo
    public function createIngredientAction(): Action
    {
        return CreateAction::make('createIngredient')
            ->label('Novo Insumo')
            ->icon('heroicon-o-beaker')
            ->model(Product::class)
            ->form(fn ($form) => IngredientResource::form($form)) // 🚀 Removido o 'Form' daqui
            ->mutateFormDataUsing(fn (array $data) => array_merge($data, ['type' => 'internal']))
            ->successNotificationTitle('Insumo criado com sucesso!')
            ->slideOver();
    }

    // 2. Ação do Material de Consumo
    public function createSupplyAction(): Action
    {
        return CreateAction::make('createSupply')
            ->label('Novo Mat. de Consumo')
            ->icon('heroicon-o-archive-box')
            ->model(Product::class)
            ->form(fn ($form) => SupplyResource::form($form)) // 🚀 Removido o 'Form' daqui
            ->mutateFormDataUsing(fn (array $data) => array_merge($data, ['type' => 'supply']))
            ->successNotificationTitle('Material criado com sucesso!')
            ->slideOver();
    }

    // 3. Ação do Produto Final
    public function createProductAction(): Action
    {
        return CreateAction::make('createProduct')
            ->label('Novo Produto Final')
            ->icon('heroicon-o-star')
            ->model(Product::class)
            ->form(fn ($form) => ProductResource::form($form)) // 🚀 Removido o 'Form' daqui
            ->mutateFormDataUsing(fn (array $data) => array_merge($data, ['type' => 'resale']))
            ->successNotificationTitle('Produto criado com sucesso!')
            ->slideOver();
    }

    public function render()
    {
        return view('livewire.quick-create-menu');
    }
}
