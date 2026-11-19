<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StoreOperationMode: string implements HasLabel, HasColor, HasIcon
{
    case SCHEDULED           = 'scheduled';
    case ALWAYS_24H          = 'always_24h';
    case CLOSED_INDEFINITELY = 'closed_indefinitely';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SCHEDULED           => 'Horários Programados',
            self::ALWAYS_24H          => 'Aberto 24 Horas',
            self::CLOSED_INDEFINITELY => 'Fechado Indeterminadamente',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SCHEDULED           => 'primary',
            self::ALWAYS_24H          => 'success',
            self::CLOSED_INDEFINITELY => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SCHEDULED           => 'heroicon-o-clock',
            self::ALWAYS_24H          => 'heroicon-o-bolt',
            self::CLOSED_INDEFINITELY => 'heroicon-o-lock-closed',
        };
    }

    public static function fromFlags(bool $is24Hours, bool $isClosedIndefinitely): self
    {
        if ($isClosedIndefinitely) {
            return self::CLOSED_INDEFINITELY;
        }
        if ($is24Hours) {
            return self::ALWAYS_24H;
        }
        return self::SCHEDULED;
    }
}