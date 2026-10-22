<?php

namespace App\Filament\Resources\Products\Widgets;

use App\Services\Catalog\ProductCostingService;
use App\Enums\ProductType;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class ProductMarginWidget extends Widget
{
    public ?Model $record = null;
    public ?Model $ownerRecord = null;

    public ?array $simulatedData = null;

    protected string $view = 'filament.resources.products.widgets.product-margin-widget';
    protected int | string | array $columnSpan = 'full';

    #[On('update-margin-widget')]
    public function applySimulation(?array $simulatedData = null): void
    {
        $this->simulatedData = $simulatedData;
    }

    #[On('reset-margin-widget')]
    public function resetSimulation(): void
    {
        $this->simulatedData = null;
    }

    protected function getViewData(): array
    {
        $targetRecord = $this->record ?? $this->ownerRecord;

        if (!$targetRecord) {
            return ['marginData' => null];
        }

        $hasRecipe = $targetRecord->technicalSheets()->exists();
        $isComplexType = in_array($targetRecord->type, [
            ProductType::DISH->value,
            ProductType::COMBO->value,
            ProductType::MANUFACTURED->value,
            ProductType::RESALE->value,
        ]);

        if (!$isComplexType && !$hasRecipe) {
            return ['marginData' => null];
        }

        $marginData = ProductCostingService::getMarginData($targetRecord);

        // Se houver simulação ativa pelo usuário no Simulador
        if ($this->simulatedData) {
            $costDiff = $this->simulatedData['cost'] - $marginData['base_cost'];

            $marginData['sell_price']          = $this->simulatedData['sell_price'];
            $marginData['base_cost']           = $this->simulatedData['cost'];
            $marginData['contribution_margin'] = $this->simulatedData['contribution_margin'];
            $marginData['margin_percentage']   = $this->simulatedData['margin_percentage'];
            $marginData['marginPercentage']    = $this->simulatedData['margin_percentage'];
            $marginData['simples_amount']      = $this->simulatedData['simples_amount'];
            $marginData['card_fee_amount']     = $this->simulatedData['card_fee_amount'];
            $marginData['total_variables']     = $this->simulatedData['total_variable_costs'];
            $marginData['is_simulated']        = true;

            $marginData['channels']['dine_in']['price']               = $this->simulatedData['sell_price'];
            $marginData['channels']['dine_in']['cmv']                 += $costDiff;
            $marginData['channels']['dine_in']['contribution_margin'] = $this->simulatedData['contribution_margin'];
            $marginData['channels']['dine_in']['margin_percentage']   = $this->simulatedData['margin_percentage'];
        }

        return [
            'record'     => $targetRecord,
            'marginData' => $marginData,
        ];
    }
}
