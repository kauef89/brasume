<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EmploymentType: string implements HasLabel, HasColor, HasIcon
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
            self::FREELANCE_FIXED => 'Diarista Fixo',
            self::FREELANCER      => 'Freelancer Pontual',
            self::PARTNER         => 'Sócio',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CLT             => 'info',
            self::INTERN          => 'gray',
            self::PJ              => 'primary',
            self::FREELANCE_FIXED => 'success',
            self::FREELANCER      => 'warning',
            self::PARTNER         => 'purple',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::CLT             => 'heroicon-o-briefcase',
            self::INTERN          => 'heroicon-o-academic-cap',
            self::PJ              => 'heroicon-o-building-office-2',
            self::FREELANCE_FIXED => 'heroicon-o-calendar-days',
            self::FREELANCER      => 'heroicon-o-bolt',
            self::PARTNER         => 'heroicon-o-user-group',
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