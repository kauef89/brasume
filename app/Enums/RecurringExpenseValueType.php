<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RecurringExpenseValueType: string implements HasLabel, HasColor, HasIcon
{
    case FIXED    = 'fixed';
    case VARIABLE = 'variable';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FIXED    => 'Fixo (Aluguel, Internet, etc.)',
            self::VARIABLE => 'Variável (Luz, Água, Gás, etc.)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FIXED    => 'info',
            self::VARIABLE => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::FIXED    => 'heroicon-o-lock-closed',
            self::VARIABLE => 'heroicon-o-variable',
        };
    }
}
