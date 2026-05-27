<?php

namespace App\Filament\Resources\Products\Widgets;

use App\Services\ProductCostingService;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class ProductMarginWidget extends Widget
{
    public ?Model $record = null;
    public ?Model $ownerRecord = null; // 🚀 Captura o produto se o widget estiver "preso" dentro de um RelationManager!

    public ?array $simulatedData = null;

    protected string $view = 'filament.resources.products.widgets.product-margin-widget';
    protected int | string | array $columnSpan = 'full';

    #[On('update-margin-widget')]
    public function applySimulation(?array $simulatedData = null)
    {
        $this->simulatedData = $simulatedData;
    }

    #[On('reset-margin-widget')]
    public function resetSimulation()
    {
        $this->simulatedData = null;
    }

    protected function getViewData(): array
    {
        // 1. Identifica o alvo (seja na página principal ou no RelationManager)
        $targetRecord = $this->record ?? $this->ownerRecord;

        if (!$targetRecord) {
            return ['marginData' => null];
        }

        // 🚀 REGRA DE VISIBILIDADE:
        // Ocultamos o widget em insumos simples (sem ficha técnica).
        // Ele só aparece se for um tipo de venda (dish, combo, resale) OU se tiver uma receita (preparado).
        $hasRecipe = $targetRecord->technicalSheets()->exists();
        $isComplexType = in_array($targetRecord->type, ['dish', 'combo', 'manufactured', 'resale']);

        if (!$isComplexType && !$hasRecipe) {
            return ['marginData' => null];
        }

        // 🚀 PRIORIZAÇÃO DO CUSTO REAL:
        // Se o produto tiver ficha técnica, forçamos o cálculo recursivo para que o base_cost
        // reflita a soma dos ingredientes (os R$ 4,68 do seu suco) e não o valor zerado do banco.
        if ($hasRecipe) {
            $realCost = ProductCostingService::calculateBaseCost($targetRecord);
            $targetRecord->base_cost = $realCost;
        }

        // Agora calculamos a margem com o custo já atualizado no objeto
        $marginData = ProductCostingService::getMarginData($targetRecord);

        // Aplica os dados de simulação se o usuário estiver mexendo no simulador
        if ($this->simulatedData) {
            $costDifference = $this->simulatedData['cost'] - $marginData['base_cost'];
            $marginData['cost_table'] += $costDifference;
            $marginData['cost_counter'] += $costDifference;
            $marginData['cost_delivery'] += $costDifference;

            $marginData['sell_price'] = $this->simulatedData['sell_price'];
            $marginData['base_cost'] = $this->simulatedData['cost'];
            $marginData['net_profit'] = $this->simulatedData['net_profit'];
            $marginData['margin_percentage'] = $this->simulatedData['margin_percentage'];
            $marginData['contribution_margin'] = $this->simulatedData['contribution_margin'];

            $marginData['simples_amount'] = $this->simulatedData['simples_amount'];
            $marginData['icms_amount'] = $this->simulatedData['icms_amount'];
            $marginData['card_fee_amount'] = $this->simulatedData['card_fee_amount'];
            $marginData['total_variables'] = $this->simulatedData['total_variable_costs'];
        }

        return [
            'record' => $targetRecord,
            'marginData' => $marginData,
        ];
    }
}
