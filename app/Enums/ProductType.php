<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasLabel, HasColor, HasIcon
{
    // Itens de Venda (Cardápio / PDV)
    case DISH         = 'dish';
    case COMBO        = 'combo';
    case RESALE       = 'resale';
    case SERVICE      = 'service';

    // Itens de Cozinha / Produção (Estoque Interno)
    case MANUFACTURED = 'manufactured';
    case INGREDIENT   = 'ingredient';

    // Suprimentos e Insumos Operacionais
    case PACKAGING    = 'packaging';
    case CONSUMABLE   = 'consumable';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DISH         => 'Prato / Cardápio',
            self::COMBO        => 'Combo / Kit',
            self::RESALE       => 'Item de Revenda',
            self::SERVICE      => 'Serviço / Taxa',
            self::MANUFACTURED => 'Pré-Preparo (Produção Própria)',
            self::INGREDIENT   => 'Insumo / Matéria-Prima',
            self::PACKAGING    => 'Embalagem',
            self::CONSUMABLE   => 'Uso e Consumo (Operação)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DISH         => 'primary',
            self::COMBO        => 'warning',
            self::RESALE       => 'success',
            self::SERVICE      => 'info',
            self::MANUFACTURED => 'purple',
            self::INGREDIENT   => 'amber',
            self::PACKAGING    => 'sky',
            self::CONSUMABLE   => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DISH         => 'heroicon-o-cake',
            self::COMBO        => 'heroicon-o-square-3-stack-3d',
            self::RESALE       => 'heroicon-o-shopping-bag',
            self::SERVICE      => 'heroicon-o-wrench',
            self::MANUFACTURED => 'heroicon-o-beaker',
            self::INGREDIENT   => 'heroicon-o-scale',
            self::PACKAGING    => 'heroicon-o-archive-box',
            self::CONSUMABLE   => 'heroicon-o-sparkles',
        };
    }

    // ==========================================
    // MÉTODOS DE DOMÍNIO (Encapsulamento de Regras)
    // ==========================================

    /**
     * Define se o item é destinado à venda no Cardápio / PDV.
     */
    public function isSellable(): bool
    {
        return in_array($this, [self::DISH, self::COMBO, self::RESALE, self::SERVICE]);
    }

    /**
     * Define se o item pode ou deve possuir Ficha Técnica / Receita.
     */
    public function canHaveRecipe(): bool
    {
        return in_array($this, [self::DISH, self::COMBO, self::MANUFACTURED]);
    }

    /**
     * Define se o item pode ser usado como ingrediente em uma ficha técnica.
     */
    public function canBeIngredient(): bool
    {
        return in_array($this, [self::INGREDIENT, self::MANUFACTURED, self::PACKAGING, self::RESALE]);
    }

    /**
     * Mapeia a macroárea de categoria correspondente para auto-seleção.
     */
    public function defaultCategoryType(): string
    {
        return match ($this) {
            self::DISH, self::COMBO, self::RESALE, self::SERVICE => 'external',
            self::MANUFACTURED, self::INGREDIENT                => 'internal',
            self::PACKAGING, self::CONSUMABLE                   => 'supply',
        };
    }
}
