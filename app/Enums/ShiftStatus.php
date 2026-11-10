<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ShiftStatus: string implements HasLabel, HasColor, HasIcon
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case RECONCILED = 'reconciled';
    case HOMOLOGATED = 'homologated';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPEN => 'Aberto',
            self::CLOSED => 'Fechado (Pendente Auditoria)',
            self::RECONCILED => 'Auditado & Conciliado',
            self::HOMOLOGATED => 'Homologado c/ Ressalva',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPEN => 'success',
            self::CLOSED => 'gray',
            self::RECONCILED => 'emerald',
            self::HOMOLOGATED => 'amber',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::OPEN => 'heroicon-m-lock-open',
            self::CLOSED => 'heroicon-m-clock',
            self::RECONCILED => 'heroicon-m-check-badge',
            self::HOMOLOGATED => 'heroicon-m-shield-exclamation',
        };
    }
}