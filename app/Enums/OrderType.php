<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderType: string implements HasLabel, HasColor, HasIcon
{
    case DELIVERY = 'delivery';
    case COUNTER  = 'counter';
    case PICKUP   = 'pickup';
    case TABLE    = 'table';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DELIVERY => 'Delivery',
            self::COUNTER  => 'Balcão',
            self::PICKUP   => 'Retirada',
            self::TABLE    => 'Mesa',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DELIVERY => 'info',
            self::COUNTER  => 'warning',
            self::PICKUP   => 'success',
            self::TABLE    => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DELIVERY => 'heroicon-o-truck',
            self::COUNTER  => 'heroicon-o-building-storefront',
            self::PICKUP   => 'heroicon-o-shopping-bag',
            self::TABLE    => 'heroicon-o-stop', // Representa a mesa
        };
    }
}
