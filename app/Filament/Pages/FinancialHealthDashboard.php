<?php

namespace App\Filament\Pages;

use App\Services\Financial\CashFlowProjectionService;
use App\Services\Financial\CmvAnalyticsService;
use App\Services\Financial\DreService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use UnitEnum;

class FinancialHealthDashboard extends Page
{
    protected string $view = 'filament.pages.financial-health-dashboard';

    protected static ?string $title = 'Saúde Financeira & DRE';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-pie';

    protected static UnitEnum | string | null $navigationGroup = 'Gestão Financeira';

    protected static ?int $navigationSort = 1;

    public string $selectedPeriod = 'this_month';
    public string $selectedRegime = 'competencia';
    public string $activeView     = 'dre'; // 'dre', 'cmv' ou 'projection'
    public int $projectionDays    = 30;    // 30 ou 60 dias

    public function setPeriod(string $period): void
    {
        $this->selectedPeriod = $period;
    }

    public function setRegime(string $regime): void
    {
        $this->selectedRegime = $regime;
    }

    public function setActiveView(string $view): void
    {
        $this->activeView = $view;
    }

    public function setProjectionDays(int $days): void
    {
        $this->projectionDays = $days;
    }

    protected function getViewData(): array
    {
        [$startDate, $endDate] = match ($this->selectedPeriod) {
            'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'quarter'    => [Carbon::now()->subDays(90)->startOfDay(), Carbon::now()->endOfDay()],
            'this_year'  => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            default      => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        };

        $dreReport   = (new DreService($startDate, $endDate, $this->selectedRegime))->calculate();
        $cmvReport   = (new CmvAnalyticsService($startDate, $endDate, $this->selectedRegime))->analyze();
        $projection  = (new CashFlowProjectionService($this->projectionDays))->project();

        return [
            'report'         => $dreReport,
            'cmv'            => $cmvReport,
            'projection'     => $projection,
            'selectedPeriod' => $this->selectedPeriod,
            'selectedRegime' => $this->selectedRegime,
            'activeView'     => $this->activeView,
            'projectionDays' => $this->projectionDays,
        ];
    }
}
