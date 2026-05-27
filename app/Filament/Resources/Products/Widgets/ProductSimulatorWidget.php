<?php

namespace App\Filament\Resources\Products\Widgets;

use App\Services\ProductCostingService;
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

        $marginData = ProductCostingService::getMarginData($targetRecord);
        $modifierGroups = $targetRecord->modifiers()->whereIn('type', ['add_on', 'ingredient'])->with('options')->get();

        $predictiveCostTotal = ProductCostingService::getPredictiveModifiersCost($targetRecord);
        $simulatedCost = $marginData['base_cost'] - $predictiveCostTotal;
        $addonSellPriceTotal = 0;

        foreach ($modifierGroups as $group) {
            $groupIsSimulated = false;
            $groupSimulatedCost = 0;

            foreach ($group->options as $option) {
                if (in_array($option->id, $this->selectedAddons)) {
                    $groupIsSimulated = true;
                    $addonSellPriceTotal += $option->price;

                    if ($option->linked_product_id) {
                        $linkedInsumo = \App\Models\Product::find($option->linked_product_id);
                        $actualCost = ProductCostingService::calculateBaseCost($linkedInsumo);
                        $groupSimulatedCost += $actualCost * ($option->linked_qty ?? 0);
                    }
                }
            }

            if ($groupIsSimulated) {
                $simulatedCost += $groupSimulatedCost;
            } else {
                if ($group->type === 'ingredient') {
                    $groupExpectedCost = 0;
                    $totalWeight = 0;
                    foreach ($group->options as $opt) {
                        if (!$opt->linked_product_id) continue;
                        $linked = \App\Models\Product::find($opt->linked_product_id);
                        if (!$linked) continue;

                        $optCost = ProductCostingService::calculateBaseCost($linked) * ($opt->linked_qty ?? 0);
                        $weight = $opt->historical_weight ?? 1;

                        $groupExpectedCost += ($optCost * $weight);
                        $totalWeight += $weight;
                    }
                    if ($totalWeight > 0) {
                        $simulatedCost += ($groupExpectedCost / $totalWeight) * ($group->max_free > 0 ? $group->max_free : 1);
                    }
                }
            }
        }

        $simplesRate = $marginData['simples_rate'] ?? 0;
        $icmsRate = ($targetRecord->is_monofasico ?? false) ? 0 : ($marginData['icms_rate'] ?? 0);
        $cardFeePercentage = $marginData['card_fee_percentage'] ?? 0;
        $totalTaxRate = $simplesRate + $icmsRate + $cardFeePercentage;

        $simulatedSellPrice = $marginData['sell_price'];

        if ($this->activeMode === null) {
            $simulatedSellPrice += $addonSellPriceTotal;
        } elseif ($this->activeMode === 'margin' && !blank($this->desiredMargin)) {
            $marginTarget = (float) str_replace(',', '.', $this->desiredMargin);
            $divisor = 1 - (($totalTaxRate + $marginTarget) / 100);
            $simulatedSellPrice = $divisor > 0 ? ($simulatedCost / $divisor) : 0;
        } elseif ($this->activeMode === 'price' && !blank($this->customPrice)) {
            $simulatedSellPrice = (float) str_replace(',', '.', $this->customPrice);
        }

        $simplesAmount = $simulatedSellPrice * ($simplesRate / 100);
        $icmsAmount = $simulatedSellPrice * ($icmsRate / 100);
        $cardFeeAmount = $simulatedSellPrice * ($cardFeePercentage / 100);
        $totalVariableCosts = $simplesAmount + $icmsAmount + $cardFeeAmount;

        $netProfit = $simulatedSellPrice - $simulatedCost - $totalVariableCosts;
        $marginPercentage = $simulatedSellPrice > 0 ? ($netProfit / $simulatedSellPrice) * 100 : 0;

        $this->dispatch('update-margin-widget', simulatedData: [
            'sell_price' => $simulatedSellPrice,
            'cost' => $simulatedCost,
            'net_profit' => $netProfit,
            'contribution_margin' => $netProfit,
            'margin_percentage' => $marginPercentage,
            'simples_amount' => $simplesAmount,
            'icms_amount' => $icmsAmount,
            'card_fee_amount' => $cardFeeAmount,
            'total_variable_costs' => $totalVariableCosts,
        ]);
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
