<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EmployeeRole: string implements HasLabel, HasColor, HasIcon
{
    case ADMIN    = 'admin';
    case MANAGER  = 'manager';
    case CASHIER  = 'cashier';
    case WAITER   = 'waiter';
    case KITCHEN  = 'kitchen';
    case COURIER  = 'courier';
    case CLEANING = 'cleaning';

    public function getLabel(): ?string
    {
        return match($this) {
            self::ADMIN    => 'Administrador',
            self::MANAGER  => 'Gerente',
            self::CASHIER  => 'Operador de Caixa',
            self::WAITER   => 'Atendente / Garçom',
            self::KITCHEN  => 'Cozinha / Produção',
            self::COURIER  => 'Entregador / Motoboy',
            self::CLEANING => 'Limpeza / Apoio',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::ADMIN    => 'danger',
            self::MANAGER  => 'primary',
            self::CASHIER  => 'info',
            self::WAITER   => 'warning',
            self::KITCHEN  => 'amber',
            self::COURIER  => 'success',
            self::CLEANING => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match($this) {
            self::ADMIN    => 'heroicon-o-shield-check',
            self::MANAGER  => 'heroicon-o-chart-bar',
            self::CASHIER  => 'heroicon-o-computer-desktop',
            self::WAITER   => 'heroicon-o-user',
            self::KITCHEN  => 'heroicon-o-fire',
            self::COURIER  => 'heroicon-o-truck',
            self::CLEANING => 'heroicon-o-sparkles',
        };
    }

    public function label(): string
    {
        return $this->getLabel() ?? $this->value;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isDeliveryRole(string|self|null $role): bool
    {
        if (!$role) return false;
        $val = $role instanceof self ? $role->value : (string) $role;
        return in_array($val, ['courier', 'driver', 'motoboy'], true);
    }
}