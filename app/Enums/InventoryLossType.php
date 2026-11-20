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
    case CASH_BREAKAGE    = 'cash_breakage';
    case TAX_LOSS         = 'tax_loss';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPERATIONAL     => 'Perda Operacional (Preparo / Quebra de Linha)',
            self::INVENTORY_AUDIT => 'Ajuste de Auditoria / Balanço',
            self::EXPIRED         => 'Validade Vencida',
            self::SPOILAGE        => 'Deterioração / Azedou',
            self::DAMAGED         => 'Avaria Física / Queda',
            self::CASH_BREAKAGE   => 'Perda por Quebra de Caixa',
            self::TAX_LOSS        => 'Perda por Imposto Indevido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPERATIONAL     => 'warning',
            self::INVENTORY_AUDIT => 'info',
            self::CASH_BREAKAGE,
            self::TAX_LOSS,
            self::EXPIRED,
            self::SPOILAGE,
            self::DAMAGED         => 'danger',
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
            self::CASH_BREAKAGE   => 'heroicon-o-banknotes',
            self::TAX_LOSS        => 'heroicon-o-receipt-percent',
        };
    }

    public function isFinancial(): bool
    {
        return in_array($this, [self::CASH_BREAKAGE, self::TAX_LOSS], true);
    }
}