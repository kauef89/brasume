<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UnitOfMeasure: string implements HasLabel
{
    case UN = 'UN';
    case KG = 'KG';
    case G  = 'G';
    case LT = 'LT';
    case ML = 'ML';
    case CX = 'CX';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::UN => 'Unidade (UN)',
            self::KG => 'Quilograma (KG)',
            self::G  => 'Grama (G)',
            self::LT => 'Litro (LT)',
            self::ML => 'Mililitro (ML)',
            self::CX => 'Caixa (CX)',
        };
    }
}
