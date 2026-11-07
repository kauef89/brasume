<?php

namespace App\Enums;

enum EmployeeRole: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case CASHIER = 'cashier';
    case WAITER = 'waiter';
    case KITCHEN = 'kitchen';
    case DRIVER = 'driver';     // Padrão operacional / Spatie role
    case COURIER = 'courier';   // Alias logístico
    case CLEANING = 'cleaning';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrador',
            self::MANAGER => 'Gerente',
            self::CASHIER => 'Caixa',
            self::WAITER => 'Atendente',
            self::KITCHEN => 'Cozinha',
            self::DRIVER, self::COURIER => 'Entregador',
            self::CLEANING => 'Limpeza',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Verifica se o papel informado corresponde a entregador/motoboy.
     */
    public static function isDeliveryRole(string|self|null $role): bool
    {
        if (!$role) return false;
        $val = $role instanceof self ? $role->value : (string) $role;
        return in_array($val, ['driver', 'courier', 'motoboy'], true);
    }
}
