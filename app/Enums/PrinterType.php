<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PrinterType: string implements HasLabel, HasIcon
{
    case RECEIPT = 'receipt';
    case LABEL   = 'label';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::RECEIPT => 'Térmica Padrão (Comandas / Recibos)',
            self::LABEL   => 'Etiqueta Adesiva (Gôndola / Validade)',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::RECEIPT => 'heroicon-o-printer',
            self::LABEL   => 'heroicon-o-tag',
        };
    }
}
