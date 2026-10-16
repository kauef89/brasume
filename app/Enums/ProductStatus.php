<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ProductStatus: string implements HasLabel, HasColor, HasIcon
{
    case AVAILABLE    = 'available';
    case OUT_OF_STOCK = 'out_of_stock';
    case PAUSED       = 'paused';
    case ARCHIVED     = 'archived';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::AVAILABLE    => 'Disponível',
            self::OUT_OF_STOCK => 'Esgotado',
            self::PAUSED       => 'Pausado',
            self::ARCHIVED     => 'Arquivado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::AVAILABLE    => 'success',
            self::OUT_OF_STOCK => 'danger',
            self::PAUSED       => 'warning',
            self::ARCHIVED     => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::AVAILABLE    => 'heroicon-o-check-circle',
            self::OUT_OF_STOCK => 'heroicon-o-x-circle',
            self::PAUSED       => 'heroicon-o-pause-circle',
            self::ARCHIVED     => 'heroicon-o-archive-box-x-mark',
        };
    }
}
