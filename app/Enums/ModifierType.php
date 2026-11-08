<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ModifierType: string implements HasLabel, HasColor, HasIcon
{
    case OPTION      = 'option';       // Antigo 'ingredient': escolhas estruturais (Pão, Ponto da Carne, Sabores)
    case ADD_ON      = 'add_on';       // Adicionais extras pagos (Bacon Extra, Hambúrguer Smash Extra)
    case OBSERVATION = 'observation';  // Observações sem custo/estoque (Sem Cebola, Molho à Parte)

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPTION      => 'Opção / Escolha',
            self::ADD_ON      => 'Adicional / Extra',
            self::OBSERVATION => 'Observação',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPTION      => 'info',
            self::ADD_ON      => 'success',
            self::OBSERVATION => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::OPTION      => 'heroicon-o-adjustments-horizontal',
            self::ADD_ON      => 'heroicon-o-plus-circle',
            self::OBSERVATION => 'heroicon-o-chat-bubble-bottom-center-text',
        };
    }

    public function hasPricingRules(): bool
    {
        return in_array($this, [self::OPTION, self::ADD_ON]);
    }
}