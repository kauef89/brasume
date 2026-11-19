<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ConsumptionChannelScope: string implements HasLabel, HasColor, HasIcon
{
    case ALL      = 'all';
    case DINE_IN  = 'dine_in';
    case TAKEAWAY = 'takeaway';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ALL      => 'Salão & Viagem',
            self::DINE_IN  => 'Apenas Salão',
            self::TAKEAWAY => 'Apenas Viagem',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ALL      => 'gray',
            self::DINE_IN  => 'info',
            self::TAKEAWAY => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ALL      => 'heroicon-o-arrows-right-left',
            self::DINE_IN  => 'heroicon-o-building-storefront',
            self::TAKEAWAY => 'heroicon-o-shopping-bag',
        };
    }

    public function appliesToDineIn(): bool
    {
        return in_array($this, [self::ALL, self::DINE_IN], true);
    }

    public function appliesToTakeaway(): bool
    {
        return in_array($this, [self::ALL, self::TAKEAWAY], true);
    }

    public static function fromFlags(bool $isDineIn, bool $isTakeaway): self
    {
        if ($isDineIn && !$isTakeaway) {
            return self::DINE_IN;
        }
        if (!$isDineIn && $isTakeaway) {
            return self::TAKEAWAY;
        }
        return self::ALL;
    }
}