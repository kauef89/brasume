<?php

namespace App\Filament\Pages;

use App\Services\Analytics\DailyCommandCenterService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Centro de Comando Diário';

    protected static ?string $navigationLabel = 'Visão do Dia';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.pages.dashboard';

    public string $selectedDate = '';

    public function mount(): void
    {
        $this->selectedDate = Carbon::today()->toDateString();
    }

    public function setDatePreset(string $preset): void
    {
        $this->selectedDate = match ($preset) {
            'yesterday' => Carbon::yesterday()->toDateString(),
            'tomorrow'  => Carbon::tomorrow()->toDateString(),
            default     => Carbon::today()->toDateString(),
        };
    }

    protected function getViewData(): array
    {
        $date = $this->selectedDate
            ? Carbon::parse($this->selectedDate)
            : Carbon::today();

        $briefing = (new DailyCommandCenterService())->build($date);

        return [
            'briefing'     => $briefing,
            'selectedDate' => $date->toDateString(),
        ];
    }
}
