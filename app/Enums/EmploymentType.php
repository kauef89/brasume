<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmploymentType: string implements HasLabel
{
    case CLT             = 'clt';
    case INTERN          = 'intern';
    case PJ              = 'pj';
    case FREELANCE_FIXED = 'freelance_fixed';
    case FREELANCER      = 'freelancer';
    case PARTNER         = 'partner';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CLT             => 'CLT',
            self::INTERN          => 'Estagiário',
            self::PJ              => 'PJ',
            self::FREELANCE_FIXED => 'Fixo (Diarista Contínuo)',
            self::FREELANCER      => 'Freelancer (Diarista Pontual)',
            self::PARTNER         => 'Sócio',
        };
    }

    public function isClt(): bool
    {
        return $this === self::CLT;
    }

    public function isFreelancer(): bool
    {
        return in_array($this, [self::FREELANCER, self::FREELANCE_FIXED]);
    }
}