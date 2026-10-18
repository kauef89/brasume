<?php

namespace App\Enums;

enum EmployeeRole: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case CASHIER = 'cashier';
    case WAITER = 'waiter';
    case KITCHEN = 'kitchen';
    case DRIVER = 'driver'; // Motoboy/Entregador
    case CLEANING = 'cleaning';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrador',
            self::MANAGER => 'Gerente',
            self::CASHIER => 'Caixa',
            self::WAITER => 'Atendente / Garçom',
            self::KITCHEN => 'Cozinha',
            self::DRIVER => 'Entregador / Motoboy',
            self::CLEANING => 'Limpeza',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
