<?php

namespace App\Filament\Pages;

use App\Services\Financial\CashFlowProjectionService;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class CashFlowDashboard extends Page
{
    protected string $view = 'filament.pages.cash-flow-dashboard';

    protected static ?string $title = 'Fluxo de Caixa';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static UnitEnum | string | null $navigationGroup = 'Gestão Financeira';

    protected static ?int $navigationSort = 3;

    public int $projectionDays = 30; // 30 ou 60 dias

    public function setProjectionDays(int $days): void
    {
        $this->projectionDays = $days;
    }

    protected function getViewData(): array
    {
        $projection = (new CashFlowProjectionService($this->projectionDays))->project();

        return [
            'projection'     => $projection,
            'projectionDays' => $this->projectionDays,
        ];
    }
}
