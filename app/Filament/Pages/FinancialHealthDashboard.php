<?php

namespace App\Filament\Pages;

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
    public string $activeView     = 'dre'; // 'dre' ou 'cmv'

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

    protected function getViewData(): array
    {
        [$startDate, $endDate] = match ($this->selectedPeriod) {
            'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'quarter'    => [Carbon::now()->subDays(90)->startOfDay(), Carbon::now()->endOfDay()],
            'this_year'  => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            default      => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        };

        $dreReport = (new DreService($startDate, $endDate, $this->selectedRegime))->calculate();
        $cmvReport = (new CmvAnalyticsService($startDate, $endDate, $this->regime ?? $this->selectedRegime))->analyze();

        return [
            'report'         => $dreReport,
            'cmv'            => $cmvReport,
            'selectedPeriod' => $this->selectedPeriod,
            'selectedRegime' => $this->selectedRegime,
            'activeView'     => $this->activeView,
        ];
    }
}
