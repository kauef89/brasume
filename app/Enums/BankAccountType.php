<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BankAccountType: string implements HasLabel, HasColor, HasIcon
{
    case CHECKING          = 'checking';
    case SAVINGS           = 'savings';
    case CREDIT_CARD       = 'credit_card';
    case VAULT             = 'vault';
    case INVESTMENT        = 'investment';
    case REVENUE_DEDUCTION = 'revenue_deduction';
    case TRANSIT           = 'transit';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CHECKING          => 'Conta Corrente',
            self::SAVINGS           => 'Conta Poupança',
            self::CREDIT_CARD       => 'Cartão de Crédito',
            self::VAULT             => 'Cofre Físico (Gaveta / Caixa)',
            self::INVESTMENT        => 'Investimento / Reserva',
            self::REVENUE_DEDUCTION => 'Dedutora de Faturamento (Perdas / Cashback / Cortesias)',
            self::TRANSIT           => 'Conta Transitória / Compensação',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CHECKING          => 'success',
            self::SAVINGS           => 'info',
            self::CREDIT_CARD       => 'danger',
            self::VAULT             => 'warning',
            self::INVESTMENT        => 'purple',
            self::REVENUE_DEDUCTION => 'rose',
            self::TRANSIT           => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::CHECKING          => 'heroicon-o-building-library',
            self::SAVINGS           => 'heroicon-o-banknotes',
            self::CREDIT_CARD       => 'heroicon-o-credit-card',
            self::VAULT             => 'heroicon-o-lock-closed',
            self::INVESTMENT        => 'heroicon-o-arrow-trending-up',
            self::REVENUE_DEDUCTION => 'heroicon-o-receipt-percent',
            self::TRANSIT           => 'heroicon-o-arrows-right-left',
        };
    }

    /**
     * Determina se a conta representa fundos de tesouraria física/bancária
     * ou se é uma conta virtual de ajuste contábil / dedução.
     */
    public function isRealFunds(): bool
    {
        return match ($this) {
            self::REVENUE_DEDUCTION, self::TRANSIT => false,
            default => true,
        };
    }
}
