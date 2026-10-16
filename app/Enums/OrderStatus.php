<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel, HasColor, HasIcon
{
    case PENDING    = 'pending';
    case PREPARING  = 'preparing';
    case READY      = 'ready';
    case DISPATCHED = 'dispatched';
    case DELIVERED  = 'delivered';
    case COMPLETED  = 'completed';
    case CANCELLED  = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING    => 'Pendente',
            self::PREPARING  => 'Preparando',
            self::READY      => 'Pronto',
            self::DISPATCHED => 'Saiu para Entrega',
            self::DELIVERED  => 'Entregue',
            self::COMPLETED  => 'Finalizado',
            self::CANCELLED  => 'Cancelado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING    => 'gray',
            self::PREPARING  => 'warning',
            self::READY      => 'info',
            self::DISPATCHED => 'indigo',
            self::DELIVERED, self::COMPLETED => 'success',
            self::CANCELLED  => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING    => 'heroicon-o-clock',
            self::PREPARING  => 'heroicon-o-fire',
            self::READY      => 'heroicon-o-bell-alert',
            self::DISPATCHED => 'heroicon-o-truck',
            self::DELIVERED, self::COMPLETED => 'heroicon-o-check-badge',
            self::CANCELLED  => 'heroicon-o-x-circle',
        };
    }
}
