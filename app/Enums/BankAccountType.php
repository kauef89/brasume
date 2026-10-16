<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BankAccountType: string implements HasLabel, HasColor, HasIcon
{
    case VAULT      = 'vault';
    case CHECKING   = 'checking';
    case SAVINGS    = 'savings';
    case INVESTMENT = 'investment';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::VAULT      => 'Cofre (Dinheiro Físico)',
            self::CHECKING   => 'Conta Corrente',
            self::SAVINGS    => 'Poupança',
            self::INVESTMENT => 'Investimento / Reserva',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::VAULT      => 'warning',
            self::CHECKING   => 'success',
            self::SAVINGS    => 'info',
            self::INVESTMENT => 'purple',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::VAULT      => 'heroicon-o-lock-closed',
            self::CHECKING   => 'heroicon-o-building-library',
            self::SAVINGS    => 'heroicon-o-banknotes',
            self::INVESTMENT => 'heroicon-o-arrow-trending-up',
        };
    }
}
