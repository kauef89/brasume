<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmploymentType: string implements HasLabel
{
    case CLT = 'clt';
    case CLT_44 = 'clt_44';
    case CLT_40 = 'clt_40';
    case INTERN = 'intern';
    case PJ = 'pj';
    case FREELANCE_FIXED = 'freelance_fixed';
    case FREELANCER = 'freelancer';
    case PARTNER = 'partner';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CLT => 'CLT',
            self::CLT_44 => 'CLT (44h)',
            self::CLT_40 => 'CLT (40h)',
            self::INTERN => 'Estagiário',
            self::PJ => 'Prestador de Serviços (PJ)',
            self::FREELANCE_FIXED => 'Freelancer Fixo',
            self::FREELANCER => 'Freelancer Eventual',
            self::PARTNER => 'Sócio / Proprietário',
        };
    }
}
