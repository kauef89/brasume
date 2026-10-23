<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AcquirerMatchStatus: string implements HasLabel, HasIcon, HasColor
{
    case MATCHED   = 'matched';
    case AMBIGUOUS = 'ambiguous';
    case ORPHAN    = 'orphan';
    case IGNORED   = 'ignored';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MATCHED   => 'Conciliada',
            self::AMBIGUOUS => 'Dúvida (Múltiplos Pedidos)',
            self::ORPHAN    => 'Órfã (Sem Pedido no Caixa)',
            self::IGNORED   => 'Ignorada',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::MATCHED   => 'heroicon-o-check-circle',
            self::AMBIGUOUS => 'heroicon-o-question-mark-circle',
            self::ORPHAN    => 'heroicon-o-exclamation-triangle',
            self::IGNORED   => 'heroicon-o-x-circle',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MATCHED   => 'success',
            self::AMBIGUOUS => 'warning',
            self::ORPHAN    => 'danger',
            self::IGNORED   => 'gray',
        };
    }
}
