<?php

namespace App\Enums;

use Carbon\Carbon;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PayrollReferenceType: string implements HasLabel, HasColor, HasIcon
{
    case MONTHLY           = 'monthly';
    case THIRTEENTH_FIRST  = '13a';
    case THIRTEENTH_SECOND = '13b';
    case VACATION          = 'vacation';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MONTHLY           => 'Folha Mensal',
            self::THIRTEENTH_FIRST  => '13º Salário (1ª Parcela)',
            self::THIRTEENTH_SECOND => '13º Salário (2ª Parcela)',
            self::VACATION          => 'Férias',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MONTHLY           => 'primary',
            self::THIRTEENTH_FIRST  => 'info',
            self::THIRTEENTH_SECOND => 'success',
            self::VACATION          => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::MONTHLY           => 'heroicon-o-calendar',
            self::THIRTEENTH_FIRST,
            self::THIRTEENTH_SECOND => 'heroicon-o-gift',
            self::VACATION          => 'heroicon-o-sun',
        };
    }

    public function buildPeriod(int $year, int $month): string
    {
        return match ($this) {
            self::MONTHLY           => sprintf('%04d-%02d', $year, $month),
            self::THIRTEENTH_FIRST  => sprintf('%04d-13A', $year),
            self::THIRTEENTH_SECOND => sprintf('%04d-13B', $year),
            self::VACATION          => sprintf('%04d-%02d-FER', $year, $month),
        };
    }

    public function defaultPaymentDate(int $year, int $month, ?Carbon $vacationStart = null): string
    {
        if ($this === self::VACATION) {
            return $vacationStart
                ? $vacationStart->copy()->subDays(2)->format('Y-m-d')
                : Carbon::create($year, $month, 1)->format('Y-m-d');
        }

        return match ($this) {
            self::THIRTEENTH_FIRST  => Carbon::create($year, 11, 30)->format('Y-m-d'),
            self::THIRTEENTH_SECOND => Carbon::create($year, 12, 20)->format('Y-m-d'),
            self::MONTHLY           => Carbon::create($year, $month, 1)->addMonth()->startOfMonth()->addDays(4)->format('Y-m-d'),
        };
    }

    public static function fromPeriod(string $period): self
    {
        if (str_ends_with($period, '-FER')) {
            return self::VACATION;
        }
        if (str_ends_with($period, '-13A')) {
            return self::THIRTEENTH_FIRST;
        }
        if (str_ends_with($period, '-13B')) {
            return self::THIRTEENTH_SECOND;
        }
        return self::MONTHLY;
    }

    public static function formatPeriod(string $period): string
    {
        if (preg_match('/^(20\d{2})-(\d{2})-FER$/', $period, $m)) {
            return "{$m[2]}/{$m[1]} (Férias)";
        }
        if (preg_match('/^(20\d{2})-13A$/', $period, $m)) {
            return "11/{$m[1]} (13º - 1ª Parc.)";
        }
        if (preg_match('/^(20\d{2})-13B$/', $period, $m)) {
            return "12/{$m[1]} (13º - 2ª Parc.)";
        }
        if (preg_match('/^(20\d{2})-(\d{2})$/', $period, $m)) {
            return "{$m[2]}/{$m[1]}";
        }
        return $period;
    }

    public static function toCarbon(string $period): Carbon
    {
        if (preg_match('/^(20\d{2})-13A$/', $period, $m)) {
            return Carbon::create((int) $m[1], 11, 1);
        }
        if (preg_match('/^(20\d{2})-13B$/', $period, $m)) {
            return Carbon::create((int) $m[1], 12, 1);
        }
        if (preg_match('/^(20\d{2})-(\d{2})/', $period, $m)) {
            return Carbon::create((int) $m[1], (int) $m[2], 1);
        }
        return Carbon::parse($period);
    }

    public static function formatLongTitle(string $period): string
    {
        $type = self::fromPeriod($period);
        $date = self::toCarbon($period);
        $monthYear = ucfirst($date->translatedFormat('F / Y'));

        return match ($type) {
            self::THIRTEENTH_FIRST  => "13º Salário (1ª Parcela) - {$monthYear}",
            self::THIRTEENTH_SECOND => "13º Salário (2ª Parcela) - {$monthYear}",
            self::VACATION          => "Recibo de Férias - {$monthYear}",
            self::MONTHLY           => "Folha de Pagamento - {$monthYear}",
        };
    }
}