<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CourierPaymentModel: string implements HasLabel, HasColor
{
    case FIXED = 'fixed';
    case NEIGHBORHOOD = 'neighborhood';
    case DISTANCE = 'distance';

    public function getLabel(): string
    {
        return match ($this) {
            self::FIXED => 'Taxa Fixa por Corrida',
            self::NEIGHBORHOOD => 'Por Bairro de Entrega',
            self::DISTANCE => 'Por Km Rodado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::FIXED => 'info',
            self::NEIGHBORHOOD => 'warning',
            self::DISTANCE => 'success',
        };
    }
}
