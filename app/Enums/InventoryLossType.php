<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InventoryLossType: string implements HasLabel, HasColor, HasIcon
{
    case OPERATIONAL      = 'operational';
    case INVENTORY_AUDIT  = 'inventory_audit';
    case EXPIRED          = 'expired';
    case SPOILAGE         = 'spoilage';
    case DAMAGED          = 'damaged';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPERATIONAL     => 'Perda Operacional (Preparo / Quebra de Linha)',
            self::INVENTORY_AUDIT => 'Ajuste de Auditoria / Balanço',
            self::EXPIRED         => 'Validade Vencida',
            self::SPOILAGE        => 'Deterioração / Azedou',
            self::DAMAGED         => 'Avaria Física / Queda',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPERATIONAL     => 'warning',
            self::INVENTORY_AUDIT => 'info',
            self::EXPIRED, self::SPOILAGE, self::DAMAGED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::OPERATIONAL     => 'heroicon-o-fire',
            self::INVENTORY_AUDIT => 'heroicon-o-clipboard-document-check',
            self::EXPIRED         => 'heroicon-o-calendar-days',
            self::SPOILAGE        => 'heroicon-o-trash',
            self::DAMAGED         => 'heroicon-o-exclamation-triangle',
        };
    }
}
