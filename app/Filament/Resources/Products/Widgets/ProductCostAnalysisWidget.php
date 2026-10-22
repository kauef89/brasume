<?php

namespace App\Filament\Resources\Products\Widgets;

use App\Services\Catalog\ProductCostingService;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class ProductCostAnalysisWidget extends Widget
{
    public ?Model $record = null;
    public ?Model $ownerRecord = null;
    public int $selectedDays = 30;

    protected string $view = 'filament.resources.products.widgets.product-cost-analysis-widget';
    protected int | string | array $columnSpan = 'full';

    #[On('update-margin-widget')]
    public function refreshAnalysis(): void
    {
        // Auto-refresh quando a ficha técnica ou o preço mudar
    }

    public function setDays(int $days): void
    {
        $this->selectedDays = $days;
    }

    protected function getViewData(): array
    {
        $target = $this->record ?? $this->ownerRecord;

        if (!$target || !in_array($target->type, ['dish', 'combo', 'manufactured', 'resale'])) {
            return ['analysis' => null];
        }

        return [
            'record'   => $target,
            'analysis' => ProductCostingService::getCostVarianceAnalysis($target, $this->selectedDays),
        ];
    }
}
