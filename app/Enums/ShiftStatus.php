<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ShiftStatus: string implements HasLabel, HasColor, HasIcon
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPEN => 'Aberto',
            self::CLOSED => 'Fechado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPEN => 'success',
            self::CLOSED => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::OPEN => 'heroicon-m-lock-open',
            self::CLOSED => 'heroicon-m-lock-closed',
        };
    }
}