<?php

namespace App\Filament\Resources\Products\Widgets;

use App\Services\Catalog\ProductCostingService;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class ProductSimulatorWidget extends Widget
{
    public ?Model $record = null;
    public ?Model $ownerRecord = null; // 🚀 Proteção RelationManager

    public array $selectedAddons = [];
    public ?string $customPrice = null;
    public ?string $desiredMargin = null;
    public ?string $activeMode = null;

    protected string $view = 'filament.resources.products.widgets.product-simulator-widget';
    protected int | string | array $columnSpan = 'full';

    public function updatedCustomPrice($value)
    {
        $this->desiredMargin = null;
        if (blank($value)) {
            $this->activeMode = null;
            $this->customPrice = null;
        } else {
            $this->activeMode = 'price';
            $this->customPrice = preg_replace('/[^0-9,]/', '', $value);
        }
        $this->recalculateAndSync();
    }

    public function updatedDesiredMargin($value)
    {
        $this->customPrice = null;
        if (blank($value)) {
            $this->activeMode = null;
            $this->desiredMargin = null;
        } else {
            $this->activeMode = 'margin';
            $this->desiredMargin = preg_replace('/[^0-9,]/', '', $value);
        }
        $this->recalculateAndSync();
    }

    public function updatedSelectedAddons()
    {
        $this->recalculateAndSync();
    }

    public function recalculateAndSync()
    {
        $targetRecord = $this->record ?? $this->ownerRecord;
        if (!$targetRecord) return;

        $customValue = null;
        if ($this->activeMode === 'margin' && !blank($this->desiredMargin)) {
            $customValue = (float) str_replace(',', '.', $this->desiredMargin);
        } elseif ($this->activeMode === 'price' && !blank($this->customPrice)) {
            $customValue = (float) str_replace(',', '.', $this->customPrice);
        }

        // 🚀 O WIDGET AGORA É BURRO! Só chama o Serviço e despacha o evento.
        $simulatedData = ProductCostingService::simulatePricing(
            $targetRecord,
            $this->selectedAddons,
            $this->activeMode,
            $customValue
        );

        $this->dispatch('update-margin-widget', simulatedData: $simulatedData);
    }

    public function getViewData(): array
    {
        $targetRecord = $this->record ?? $this->ownerRecord;
        if (!$targetRecord) return [];
        return [
            'modifierGroups' => $targetRecord->modifiers()->whereIn('type', ['add_on', 'ingredient'])->with('options')->get(),
        ];
    }
}
