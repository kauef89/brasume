<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasColor;

enum DeviceType: string implements HasLabel, HasIcon, HasColor
{
    // Terminais de Aplicação (Telas / Software)
    case POS        = 'pos';
    case KDS        = 'kds';
    case TOTEM      = 'totem';
    case WAITER     = 'waiter';

    // Terminais de Pagamento (Hardware / Captura de Cartão)
    case SMART_POS  = 'smart_pos';
    case POS_PINPAD = 'pos_pinpad';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::POS        => 'Caixa',
            self::KDS        => 'Tela KDS',
            self::TOTEM      => 'Totem',
            self::WAITER     => 'Mobile',
            self::SMART_POS  => 'APOS',
            self::POS_PINPAD => 'TEF',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::POS        => 'heroicon-o-computer-desktop',
            self::KDS        => 'heroicon-o-tv',
            self::TOTEM      => 'heroicon-o-device-phone-mobile',
            self::WAITER     => 'heroicon-o-user',
            self::SMART_POS  => 'heroicon-o-calculator',
            self::POS_PINPAD => 'heroicon-o-calculator',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::POS        => 'success',
            self::KDS        => 'warning',
            self::TOTEM      => 'purple',
            self::WAITER     => 'gray',
            self::SMART_POS  => 'primary',
            self::POS_PINPAD => 'info',
        };
    }

    /**
     * Terminais físicos de cartão que geram transações em adquirentes (Sicredi, Stone, etc.).
     */
    public function isPaymentTerminal(): bool
    {
        return in_array($this, [self::SMART_POS, self::POS_PINPAD]);
    }

    /**
     * Terminais com interface de usuário que exigem emparelhamento por UUID.
     */
    public function isAppTerminal(): bool
    {
        return in_array($this, [self::POS, self::KDS, self::TOTEM, self::WAITER]);
    }
}
