<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LaborPaymentType: string implements HasLabel, HasColor, HasIcon
{
    case SALARY          = 'salary';           // Salário Mensal CLT (Aparecida, Ildo, Lotes)
    case ADVANCE         = 'advance';          // Vales e Adiantamentos Quinzenais (Djoni, vales balcão)
    case FREELANCE_DAILY = 'freelance_daily';  // Diárias de Freelancers (Beatriz, Alessandra, Josimara - R$ 100/dia)
    case DELIVERY_FEE    = 'delivery_fee';     // Repasse de Entregas / Diária de Motoboy (Djoni)
    case EXTRA_SERVICE   = 'extra_service';    // Serviços Extras / Faxina Pesada / Reforço Operacional
    case REIMBURSEMENT   = 'reimbursement';    // Reembolso de Compras Operacionais (sem ônus trabalhista)
    case SEVERANCE       = 'severance';        // Rescisão, Férias, 13º Salário

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SALARY          => 'Salário CLT',
            self::ADVANCE         => 'Vale / Adiantamento',
            self::FREELANCE_DAILY => 'Diária Freelancer',
            self::DELIVERY_FEE    => 'Repasse Entrega / Motoboy',
            self::EXTRA_SERVICE   => 'Serviço Extra / Eventual',
            self::REIMBURSEMENT   => 'Reembolso Operacional',
            self::SEVERANCE       => 'Rescisão / Férias / 13º',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SALARY          => 'success',
            self::ADVANCE         => 'warning',
            self::FREELANCE_DAILY => 'info',
            self::DELIVERY_FEE    => 'primary',
            self::EXTRA_SERVICE   => 'secondary',
            self::REIMBURSEMENT   => 'gray',
            self::SEVERANCE       => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SALARY          => 'heroicon-m-banknotes',
            self::ADVANCE         => 'heroicon-m-receipt-percent',
            self::FREELANCE_DAILY => 'heroicon-m-calendar-days',
            self::DELIVERY_FEE    => 'heroicon-m-truck',
            self::EXTRA_SERVICE   => 'heroicon-m-sparkles',
            self::REIMBURSEMENT   => 'heroicon-m-arrow-path',
            self::SEVERANCE       => 'heroicon-m-document-text',
        };
    }
}