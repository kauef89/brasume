<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CompliancePermitType: string implements HasLabel, HasColor, HasIcon
{
    case SANITARY_PERMIT   = 'sanitary_permit';    // Alvará Sanitário / Vigilância
    case FIRE_DEPT_AVCB    = 'fire_dept_avcb';     // Bombeiros / AVCB
    case CITY_HALL_LICENSE = 'city_hall_license';  // Alvará de Funcionamento / Localização
    case PEST_CONTROL      = 'pest_control';       // Dedetização / Controle de Pragas
    case GREASE_TRAP       = 'grease_trap';        // Limpeza de Coifa e Caixa de Gordura
    case WATER_TANK        = 'water_tank';         // Higienização de Reservatório de Água
    case FOOD_HANDLING     = 'food_handling';      // Curso de Manipulação / Manual de Boas Práticas
    case OCCUPATIONAL      = 'occupational';       // PGR / PCMSO (Medicina do Trabalho)
    case OTHER             = 'other';              // Outras Taxas e Licenças

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SANITARY_PERMIT   => 'Alvará Sanitário (Vigilância)',
            self::FIRE_DEPT_AVCB    => 'Bombeiros (AVCB / Funcionamento)',
            self::CITY_HALL_LICENSE => 'Alvará de Funcionamento (Prefeitura)',
            self::PEST_CONTROL      => 'Dedetização / Controle de Pragas',
            self::GREASE_TRAP       => 'Limpeza de Coifa / Caixa de Gordura',
            self::WATER_TANK        => 'Higienização da Caixa d\'Água',
            self::FOOD_HANDLING     => 'Boas Práticas / Manipulação de Alimentos',
            self::OCCUPATIONAL      => 'Segurança do Trabalho (PGR / PCMSO)',
            self::OTHER             => 'Outra Licença / Taxa',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::SANITARY_PERMIT   => 'success',
            self::FIRE_DEPT_AVCB    => 'danger',
            self::CITY_HALL_LICENSE => 'info',
            self::PEST_CONTROL      => 'warning',
            self::GREASE_TRAP       => 'gray',
            self::WATER_TANK        => 'info',
            self::FOOD_HANDLING     => 'success',
            self::OCCUPATIONAL      => 'warning',
            self::OTHER             => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SANITARY_PERMIT   => 'heroicon-o-shield-check',
            self::FIRE_DEPT_AVCB    => 'heroicon-o-fire',
            self::CITY_HALL_LICENSE => 'heroicon-o-building-library',
            self::PEST_CONTROL      => 'heroicon-o-bug-ant',
            self::GREASE_TRAP       => 'heroicon-o-wrench-screwdriver',
            self::WATER_TANK        => 'heroicon-o-beaker',
            self::FOOD_HANDLING     => 'heroicon-o-academic-cap',
            self::OCCUPATIONAL      => 'heroicon-o-user-group',
            self::OTHER             => 'heroicon-o-document-text',
        };
    }
}