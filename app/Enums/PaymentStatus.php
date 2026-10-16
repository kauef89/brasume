<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel, HasColor, HasIcon
{
    case PENDING   = 'pending';
    case PARTIAL   = 'partial';
    case PAID      = 'paid';
    case CANCELLED = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING   => 'Pendente',
            self::PARTIAL   => 'Parcialmente Pago',
            self::PAID      => 'Pago',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING   => 'warning',
            self::PARTIAL   => 'info',
            self::PAID      => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING   => 'heroicon-o-clock',
            self::PARTIAL   => 'heroicon-o-adjustments-horizontal',
            self::PAID      => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }
}
