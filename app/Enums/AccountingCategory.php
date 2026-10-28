<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AccountingCategory: string implements HasLabel, HasColor, HasIcon
{
    case INGREDIENTS    = 'ingredients';     // Insumos, Matéria-Prima, Carnes, Queijos (CMV)
    case PACKAGING      = 'packaging';       // Embalagens, Descartáveis, Sacolas (CMV)
    case PAYROLL        = 'payroll';         // Pessoal, Folha, Diaristas, Vales, Saúde
    case UTILITIES      = 'utilities';       // Água, Energia, Gás, Internet/Unifique, Aluguel
    case MAINTENANCE    = 'maintenance';     // Manutenção Predial e de Máquinas/Equipamentos
    case MARKETING      = 'marketing';       // Tráfego Pago, Impressos, Panfletos
    case ADMINISTRATIVE = 'administrative';  // Sistemas, Software, Contabilidade, Escritório
    case CAPEX          = 'capex';           // Investimentos em Ativos / Obras / Fachada (Fora do EBITDA)
    case PARTNERS       = 'partners';        // Retiradas Pessoais e Pró-labore dos Sócios

    public function getLabel(): ?string
    {
        return match ($this) {
            self::INGREDIENTS    => '01. Insumos & Matéria-Prima (CMV)',
            self::PACKAGING      => '02. Embalagens (CMV)',
            self::PAYROLL        => '03. Pessoal & Equipe',
            self::UTILITIES      => '04. Utilidades & Ocupação (Infra)',
            self::MAINTENANCE    => '05. Manutenção & Limpeza',
            self::MARKETING      => '06. Marketing & Vendas',
            self::ADMINISTRATIVE => '07. Administrativo & Sistemas',
            self::CAPEX          => '08. Investimentos / Fachada / Ativos (CAPEX)',
            self::PARTNERS       => '09. Uso Pessoal / Sócios',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::INGREDIENTS    => 'warning',
            self::PACKAGING      => 'gray',
            self::PAYROLL        => 'info',
            self::UTILITIES      => 'danger',
            self::MAINTENANCE    => 'gray',
            self::MARKETING      => 'success',
            self::ADMINISTRATIVE => 'primary',
            self::CAPEX          => 'gray',
            self::PARTNERS       => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::INGREDIENTS    => 'heroicon-o-shopping-cart',
            self::PACKAGING      => 'heroicon-o-archive-box',
            self::PAYROLL        => 'heroicon-o-user-group',
            self::UTILITIES      => 'heroicon-o-bolt',
            self::MAINTENANCE    => 'heroicon-o-wrench-screwdriver',
            self::MARKETING      => 'heroicon-o-megaphone',
            self::ADMINISTRATIVE => 'heroicon-o-computer-desktop',
            self::CAPEX          => 'heroicon-o-building-office',
            self::PARTNERS       => 'heroicon-o-user',
        };
    }

    public function isCogs(): bool
    {
        return in_array($this, [self::INGREDIENTS, self::PACKAGING]);
    }

    public function isOperatingExpense(): bool
    {
        return in_array($this, [
            self::PAYROLL,
            self::UTILITIES,
            self::MAINTENANCE,
            self::MARKETING,
            self::ADMINISTRATIVE,
        ]);
    }

    public function isCapex(): bool
    {
        return $this === self::CAPEX;
    }

    public function isPersonal(): bool
    {
        return $this === self::PARTNERS;
    }
}
