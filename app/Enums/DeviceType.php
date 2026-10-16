<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasIcon;

enum DeviceType: string implements HasLabel, HasIcon
{
    case POS   = 'pos';
    case KDS   = 'kds';
    case TOTEM = 'totem';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::POS   => 'Caixa / Balcão (PDV)',
            self::KDS   => 'Tela de Produção (KDS)',
            self::TOTEM => 'Totem de Autoatendimento',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::POS   => 'heroicon-o-computer-desktop',
            self::KDS   => 'heroicon-o-tv',
            self::TOTEM => 'heroicon-o-device-phone-mobile',
        };
    }
}
