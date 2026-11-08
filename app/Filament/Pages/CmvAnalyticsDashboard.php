<?php

namespace App\Filament\Pages;

use App\Services\Financial\CmvAnalyticsService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use UnitEnum;

class CmvAnalyticsDashboard extends Page
{
    protected string $view = 'filament.pages.cmv-analytics-dashboard';

    protected static ?string $title = 'CMV & Cardápio';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-scale';

    protected static UnitEnum | string | null $navigationGroup = 'Gestão Financeira';

    protected static ?int $navigationSort = 2;

    public string $selectedPeriod = 'this_month';
    public string $selectedRegime = 'competencia';

    public function setPeriod(string $period): void
    {
        $this->selectedPeriod = $period;
    }

    public function setRegime(string $regime): void
    {
        $this->selectedRegime = $regime;
    }

    protected function getViewData(): array
    {
        [$startDate, $endDate] = match ($this->selectedPeriod) {
            'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'quarter'    => [Carbon::now()->subDays(90)->startOfDay(), Carbon::now()->endOfDay()],
            'this_year'  => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
            default      => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        };

        $cmvReport = (new CmvAnalyticsService($startDate, $endDate, $this->selectedRegime))->analyze();

        return [
            'cmv'            => $cmvReport,
            'selectedPeriod' => $this->selectedPeriod,
            'selectedRegime' => $this->selectedRegime,
        ];
    }
}
