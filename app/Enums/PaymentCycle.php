<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentCycle: string implements HasLabel
{
    // Removido DAILY
    case WEEKLY = 'weekly';
    case BIWEEKLY = 'biweekly';
    case BIMONTHLY = 'bimonthly';
    case QUARTERLY = 'quarterly';
    case SEMIANNUAL = 'semiannual';
    case YEARLY = 'yearly';
    case MONTHLY = 'monthly';

    public function getLabel(): ?string
    {
        return match ($this) {
            
            self::WEEKLY => 'Semanal',
            self::BIWEEKLY => 'Quinzenal',
            self::MONTHLY => 'Mensal',
            self::BIMONTHLY => 'Bimestral',
            self::QUARTERLY => 'Trimestral',
            self::SEMIANNUAL => 'Semestral',
            self::YEARLY => 'Anual',
        };
    }
}
