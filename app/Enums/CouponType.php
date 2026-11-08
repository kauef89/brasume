<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CouponType: string implements HasLabel, HasIcon, HasColor
{
    case FIXED         = 'fixed';
    case PERCENT       = 'percent';
    case FREE_DELIVERY = 'free_delivery';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FIXED         => 'Fixo (R$)',
            self::PERCENT       => 'Porcentagem (%)',
            self::FREE_DELIVERY => 'Entrega Grátis',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FIXED         => 'success',
            self::PERCENT       => 'warning',
            self::FREE_DELIVERY => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::FIXED         => 'heroicon-o-currency-dollar',
            self::PERCENT       => 'heroicon-o-receipt-percent',
            self::FREE_DELIVERY => 'heroicon-o-truck',
        };
    }
}