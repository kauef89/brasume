<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel, HasColor, HasIcon
{
    case PENDING          = 'pending';
    case PARTIAL          = 'partial';
    case PAID             = 'paid';
    case SETTLED_INTERNAL = 'settled_internal';
    case CANCELLED        = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING          => 'Pendente',
            self::PARTIAL          => 'Parcialmente Pago',
            self::PAID             => 'Pago',
            self::SETTLED_INTERNAL => 'Baixa Interna / Sem Receita',
            self::CANCELLED        => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING          => 'warning',
            self::PARTIAL          => 'info',
            self::PAID             => 'success',
            self::SETTLED_INTERNAL => 'purple',
            self::CANCELLED        => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING          => 'heroicon-o-clock',
            self::PARTIAL          => 'heroicon-o-adjustments-horizontal',
            self::PAID             => 'heroicon-o-check-circle',
            self::SETTLED_INTERNAL => 'heroicon-o-shield-check',
            self::CANCELLED        => 'heroicon-o-x-circle',
        };
    }

    /**
     * Status financeiros de pedidos com receita comercial.
     *
     * @return array<int, string>
     */
    public static function revenueValues(): array
    {
        return [
            self::PAID->value,
            self::PARTIAL->value,
        ];
    }

    /**
     * Status financeiros de baixa interna sem receita comercial.
     *
     * @return array<int, string>
     */
    public static function nonRevenueValues(): array
    {
        return [
            self::SETTLED_INTERNAL->value,
        ];
    }
}
