<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ShoppingListStatus: string implements HasLabel, HasColor, HasIcon
{
    case PENDING  = 'pending';
    case ORDERED  = 'ordered';
    case RECEIVED = 'received';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING  => 'Pendente (A Comprar)',
            self::ORDERED  => 'Pedido Realizado / Aguardando',
            self::RECEIVED => 'Recebido no Estoque',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING  => 'warning',
            self::ORDERED  => 'info',
            self::RECEIVED => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING  => 'heroicon-o-shopping-cart',
            self::ORDERED  => 'heroicon-o-clock',
            self::RECEIVED => 'heroicon-o-check-badge',
        };
    }
}
