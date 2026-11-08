<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel, HasColor, HasIcon
{
    case PENDING          = 'pending';
    case PREPARING        = 'preparing';
    case READY            = 'ready';
    case OUT_FOR_DELIVERY = 'out_for_delivery'; // Padrão da aplicação e expedidor
    case DISPATCHED       = 'dispatched';       // Alias para integrações externas
    case DELIVERED        = 'delivered';
    case CLOSED           = 'closed';           // Padrão do banco (1.691 pedidos)
    case COMPLETED        = 'completed';        // Alias
    case CANCELLED        = 'cancelled';
    case TRANSFERRED      = 'transferred';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING                  => 'Pendente',
            self::PREPARING                => 'Preparando',
            self::READY                    => 'Pronto',
            self::OUT_FOR_DELIVERY,
            self::DISPATCHED               => 'Saiu para Entrega',
            self::DELIVERED                => 'Entregue',
            self::CLOSED,
            self::COMPLETED                => 'Concluído',
            self::CANCELLED                => 'Cancelado',
            self::TRANSFERRED              => 'Transferido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING                  => 'gray',
            self::PREPARING                => 'warning',
            self::READY                    => 'info',
            self::OUT_FOR_DELIVERY,
            self::DISPATCHED               => 'indigo',
            self::DELIVERED,
            self::CLOSED,
            self::COMPLETED                => 'success',
            self::CANCELLED                => 'danger',
            self::TRANSFERRED              => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING                  => 'heroicon-o-clock',
            self::PREPARING                => 'heroicon-o-fire',
            self::READY                    => 'heroicon-o-bell-alert',
            self::OUT_FOR_DELIVERY,
            self::DISPATCHED               => 'heroicon-o-truck',
            self::DELIVERED,
            self::CLOSED,
            self::COMPLETED                => 'heroicon-o-check-badge',
            self::CANCELLED                => 'heroicon-o-x-circle',
            self::TRANSFERRED              => 'heroicon-o-arrows-right-left',
        };
    }
}