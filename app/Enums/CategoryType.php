<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CategoryType: string implements HasLabel, HasColor
{
    case EXTERNAL = 'external';
    case INTERNAL = 'internal';
    case SUPPLY   = 'supply';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EXTERNAL => 'Cardápio / Vendas (Pratos, Bebidas, Combos)',
            self::INTERNAL => 'Cozinha / Estoque (Insumos, Pré-preparos)',
            self::SUPPLY   => 'Suprimentos (Embalagens, Material de Limpeza)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EXTERNAL => 'success',
            self::INTERNAL => 'warning',
            self::SUPPLY   => 'gray',
        };
    }
}
