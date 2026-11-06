<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentCycle: string implements HasLabel
{
    case DAILY      = 'daily';
    case WEEKLY     = 'weekly';
    case BIWEEKLY   = 'biweekly';
    case MONTHLY    = 'monthly';
    case BIMONTHLY  = 'bimonthly';
    case QUARTERLY  = 'quarterly';
    case SEMIANNUAL = 'semiannual';
    case YEARLY     = 'yearly';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DAILY      => 'Diário',
            self::WEEKLY     => 'Semanal',
            self::BIWEEKLY   => 'Quinzenal',
            self::MONTHLY    => 'Mensal',
            self::BIMONTHLY  => 'Bimestral',
            self::QUARTERLY  => 'Trimestral',
            self::SEMIANNUAL => 'Semestral',
            self::YEARLY     => 'Anual',
        };
    }
}
