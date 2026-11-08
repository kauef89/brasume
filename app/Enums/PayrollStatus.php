<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PayrollStatus: string implements HasLabel, HasColor, HasIcon
{
    case DRAFT    = 'draft';
    case APPROVED = 'approved';
    case PAID     = 'paid';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT    => 'Rascunho / Aberta',
            self::APPROVED => 'Aprovada',
            self::PAID     => 'Paga / Finalizada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT    => 'warning',
            self::APPROVED => 'primary',
            self::PAID     => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT    => 'heroicon-o-pencil-square',
            self::APPROVED => 'heroicon-o-check',
            self::PAID     => 'heroicon-o-check-badge',
        };
    }
}