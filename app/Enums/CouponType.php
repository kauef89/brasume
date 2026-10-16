<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum CouponType: string implements HasLabel, HasIcon
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

    public function getIcon(): ?string
    {
        return match ($this) {
            self::FIXED         => 'heroicon-o-currency-dollar',
            self::PERCENT       => 'heroicon-o-receipt-percent',
            self::FREE_DELIVERY => 'heroicon-o-truck',
        };
    }
}
