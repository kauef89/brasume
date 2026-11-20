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
            self::DELIVERY => 'Entrega',
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
            self::COUNTER  => 'heroicon-o-bolt',
            self::PICKUP   => 'heroicon-o-shopping-bag',
            self::TABLE    => 'heroicon-o-stop',
        };
    }

    public static function resolve(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        return match (mb_strtolower(trim($value))) {
            'delivery'           => self::DELIVERY,
            'counter'            => self::COUNTER,
            'pickup', 'takeaway' => self::PICKUP,
            'table'              => self::TABLE,
            default              => self::tryFrom($value),
        };
    }

    public function badgeClasses(): string
    {
        return match ($this->getColor()) {
            'info'    => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
            'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
            'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
            'primary' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
            default   => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        };
    }
}
