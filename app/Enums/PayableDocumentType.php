<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PayableDocumentType: string implements HasLabel, HasColor, HasIcon
{
    case DARE_SC        = 'dare_sc';
    case DAS_SIMPLES    = 'das_simples';
    case DARF_INSS      = 'darf_inss';
    case FGTS_DIGITAL   = 'fgts_digital';
    case ACCOUNTING_FEE = 'accounting_fee';
    case MUNICIPAL_TAX  = 'municipal_tax';
    case GENERIC_BOLETO = 'generic_boleto';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DARE_SC        => 'DARE-SC (ICMS / ST)',
            self::DAS_SIMPLES    => 'DAS (Simples Nacional)',
            self::DARF_INSS      => 'DARF Previdenciário (INSS)',
            self::FGTS_DIGITAL   => 'GFD (FGTS Digital)',
            self::ACCOUNTING_FEE => 'Honorários Contábeis',
            self::MUNICIPAL_TAX  => 'Taxa / Guia Municipal',
            self::GENERIC_BOLETO => 'Boleto Bancário',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DARE_SC,
            self::DAS_SIMPLES,
            self::MUNICIPAL_TAX  => 'warning',
            self::DARF_INSS,
            self::FGTS_DIGITAL   => 'info',
            self::ACCOUNTING_FEE => 'primary',
            self::GENERIC_BOLETO => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DARE_SC,
            self::DAS_SIMPLES,
            self::MUNICIPAL_TAX  => 'heroicon-o-building-library',
            self::DARF_INSS,
            self::FGTS_DIGITAL   => 'heroicon-o-user-group',
            self::ACCOUNTING_FEE => 'heroicon-o-calculator',
            self::GENERIC_BOLETO => 'heroicon-o-document-text',
        };
    }

    public function defaultCategory(): AccountingCategory
    {
        return match ($this) {
            self::DARF_INSS,
            self::FGTS_DIGITAL => AccountingCategory::PAYROLL,
            default            => AccountingCategory::ADMINISTRATIVE,
        };
    }

    public function defaultSubcategory(): AccountingSubcategory
    {
        return match ($this) {
            self::DARE_SC,
            self::DAS_SIMPLES,
            self::MUNICIPAL_TAX  => AccountingSubcategory::TAXES,
            self::DARF_INSS,
            self::FGTS_DIGITAL   => AccountingSubcategory::PAYROLL,
            self::ACCOUNTING_FEE => AccountingSubcategory::ACCOUNTING,
            self::GENERIC_BOLETO => AccountingSubcategory::GENERAL,
        };
    }
}