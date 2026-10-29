<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ConciliationType: string implements HasLabel, HasColor, HasIcon
{
    case EXPENSE   = 'expense';
    case RECURRING = 'recurring';
    case PERSONAL  = 'personal';
    case TRANSFER  = 'transfer';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EXPENSE   => 'Despesa Avulsa / Lote',
            self::RECURRING => 'Conta Fixa (Recorrente)',
            self::PERSONAL  => 'Despesa Pessoal / Sócio',
            self::TRANSFER  => 'Transferência / Pgto de Fatura',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::EXPENSE   => 'primary',
            self::RECURRING => 'warning',
            self::PERSONAL  => 'info',
            self::TRANSFER  => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EXPENSE   => 'heroicon-o-document-text',
            self::RECURRING => 'heroicon-o-arrow-path',
            self::PERSONAL  => 'heroicon-o-user',
            self::TRANSFER  => 'heroicon-o-arrows-right-left',
        };
    }
}