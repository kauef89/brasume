<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PayableSettlementMode: string implements HasLabel, HasColor, HasIcon
{
    case DDA_BOLETO     = 'dda_boleto';
    case AUTO_DEBIT     = 'auto_debit';
    case PIX_REMITTANCE = 'pix_remittance';
    case MANUAL         = 'manual';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DDA_BOLETO     => 'DDA / Agenda de Boletos',
            self::AUTO_DEBIT     => 'Débito Automático',
            self::PIX_REMITTANCE => 'Malote PIX (CNAB 240)',
            self::MANUAL         => 'Pagamento Manual',
        };
    }

    public function getShortLabel(): string
    {
        return match ($this) {
            self::DDA_BOLETO     => 'DDA Banco',
            self::AUTO_DEBIT     => 'Déb. Auto',
            self::PIX_REMITTANCE => 'Malote PIX',
            self::MANUAL         => 'Manual',
        };
    }

    public function getCalendarPrefix(): string
    {
        return match ($this) {
            self::DDA_BOLETO     => '🏦 ',
            self::AUTO_DEBIT     => '🔄 ',
            self::PIX_REMITTANCE => '⚡ ',
            self::MANUAL         => '',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DDA_BOLETO     => 'info',
            self::AUTO_DEBIT     => 'purple',
            self::PIX_REMITTANCE => 'success',
            self::MANUAL         => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DDA_BOLETO     => 'heroicon-o-building-library',
            self::AUTO_DEBIT     => 'heroicon-o-arrow-path',
            self::PIX_REMITTANCE => 'heroicon-o-bolt',
            self::MANUAL         => 'heroicon-o-hand-raised',
        };
    }

    public function isBankAutomated(): bool
    {
        return in_array($this, [self::DDA_BOLETO, self::AUTO_DEBIT], true);
    }

    public function isEligibleForPixBatch(): bool
    {
        return in_array($this, [self::PIX_REMITTANCE, self::MANUAL], true);
    }
}