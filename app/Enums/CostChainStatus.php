<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CostChainStatus: string implements HasLabel, HasColor, HasIcon, HasDescription
{
    case HEALTHY       = 'healthy';        // 100% Conectado e Auditado (CMV Confiável)
    case ORPHAN_PREP   = 'orphan_prep';    // Pré-Preparo próprio sem ficha técnica cadastrada
    case ZERO_COST     = 'zero_cost';      // Insumo ou embalagem com custo zerado (R$ 0,00)
    case UNCONFIGURED  = 'unconfigured';   // Item de produção sem nenhum insumo vinculado
    case DIRECT_RESALE = 'direct_resale';  // Revenda Direta com custo de compra válido
    case EXEMPT        = 'exempt';         // Serviços, taxas ou consumo que não demandam ficha

    public function getLabel(): ?string
    {
        return match ($this) {
            self::HEALTHY       => 'Cadeia Conectada',
            self::ORPHAN_PREP   => 'Preparado sem Ficha',
            self::ZERO_COST     => 'Insumo sem Custo',
            self::UNCONFIGURED  => 'Sem Ficha Técnica',
            self::DIRECT_RESALE => 'Revenda Conectada',
            self::EXEMPT        => 'Não Aplicável',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::HEALTHY       => 'Todos os insumos e sub-preparados possuem receitas e custos válidos.',
            self::ORPHAN_PREP   => 'Contém pré-preparo próprio na receita que ainda não possui ficha técnica.',
            self::ZERO_COST     => 'Possui insumos ou embalagens com custo unitário R$ 0,00 ou sem histórico de compra.',
            self::UNCONFIGURED  => 'Item de produção que ainda não possui nenhum insumo vinculado.',
            self::DIRECT_RESALE => 'Item de revenda com custo unitário de compra válido.',
            self::EXEMPT        => 'Item que não requer ficha técnica ou controle de CMV.',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::HEALTHY, self::DIRECT_RESALE => 'success',
            self::ORPHAN_PREP                  => 'danger',   // Crítico: sub-receita inexistente
            self::ZERO_COST                    => 'warning',  // Alerta: distorce margem
            self::UNCONFIGURED                 => 'gray',
            self::EXEMPT                       => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::HEALTHY, self::DIRECT_RESALE => 'heroicon-m-check-badge',
            self::ORPHAN_PREP                  => 'heroicon-m-exclamation-triangle',
            self::ZERO_COST                    => 'heroicon-m-currency-dollar',
            self::UNCONFIGURED                 => 'heroicon-m-link-slash',
            self::EXEMPT                       => 'heroicon-m-minus-circle',
        };
    }
}