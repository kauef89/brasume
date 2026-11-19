<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ScheduleShiftType: string implements HasLabel, HasColor, HasIcon
{
    case REGULAR     = 'regular';
    case EXTRA_SHIFT = 'extra_shift';
    case DAY_OFF     = 'day_off';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::REGULAR     => 'Turno Regular',
            self::EXTRA_SHIFT => 'Turno Extra / Dobra',
            self::DAY_OFF     => 'Folga',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::REGULAR     => 'success',
            self::EXTRA_SHIFT => 'warning',
            self::DAY_OFF     => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::REGULAR     => 'heroicon-o-briefcase',
            self::EXTRA_SHIFT => 'heroicon-o-bolt',
            self::DAY_OFF     => 'heroicon-o-moon',
        };
    }

    public function isWorkingShift(): bool
    {
        return $this !== self::DAY_OFF;
    }

    public static function fromFlags(bool $isDayOff, bool $isExtraShift): self
    {
        if ($isDayOff) {
            return self::DAY_OFF;
        }
        if ($isExtraShift) {
            return self::EXTRA_SHIFT;
        }
        return self::REGULAR;
    }
}