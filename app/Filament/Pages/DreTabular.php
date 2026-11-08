<?php

namespace App\Filament\Pages;

use App\Services\Financial\DreService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class DreTabular extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-table-cells';
    protected static string | \UnitEnum | null $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'DRE Tabular';
    protected static ?string $title = 'DRE - Demonstrativo de Resultado do Exercício';
    protected static ?string $slug = 'dre-tabular';
    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.dre-tabular';

    public int $year;
    public string $regime = 'competencia'; // 'competencia' ou 'caixa'

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->year = (int) now()->year;
    }

    public function updatedYear(): void
    {
    }

    public function updatedRegime(): void
    {
    }

    public function getMonthsData(): array
    {
        $months = [];

        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::createFromDate($this->year, $m, 1)->startOfMonth();
            $end   = Carbon::createFromDate($this->year, $m, 1)->endOfMonth();

            $service = new DreService($start, $end, $this->regime);
            $months[$m] = $service->calculate();
        }

        $startYear = Carbon::createFromDate($this->year, 1, 1)->startOfYear();
        $endYear   = Carbon::createFromDate($this->year, 12, 31)->endOfYear();
        $totalYear = (new DreService($startYear, $endYear, $this->regime))->calculate();

        return [
            'months' => $months,
            'total'  => $totalYear,
        ];
    }
}