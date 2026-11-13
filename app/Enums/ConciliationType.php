<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ConciliationType: string implements HasLabel, HasColor, HasIcon
{
    case EXPENSE         = 'expense';
    case RECURRING       = 'recurring';
    case PAYROLL         = 'payroll';
    case REFUND          = 'refund';
    case TRANSFER        = 'transfer';
    case PERSONAL        = 'personal';
    case CARD_SETTLEMENT = 'card_settlement';
    case RECEIVABLE      = 'receivable';
    case CAPITAL_IN      = 'capital_in';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EXPENSE         => 'Despesa Operacional',
            self::RECURRING       => 'Conta Fixa',
            self::PAYROLL         => 'Folha & Equipe',
            self::REFUND          => 'Estorno / Devolução',
            self::TRANSFER        => 'Transferência / Fatura Cartão',
            self::PERSONAL        => 'Despesa Pessoal / Sócio',
            self::CARD_SETTLEMENT => 'Repasse de Cartão',
            self::RECEIVABLE      => 'Recebimento / Cliente',
            self::CAPITAL_IN      => 'Empréstimo / Aporte / Outros',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::EXPENSE         => 'primary',
            self::RECURRING       => 'warning',
            self::PAYROLL         => 'info',
            self::REFUND          => 'info',
            self::TRANSFER        => 'success',
            self::PERSONAL        => 'gray',
            self::CARD_SETTLEMENT => 'success',
            self::RECEIVABLE      => 'success',
            self::CAPITAL_IN      => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::EXPENSE         => 'heroicon-o-document-text',
            self::RECURRING       => 'heroicon-o-arrow-path',
            self::PAYROLL         => 'heroicon-o-users',
            self::REFUND          => 'heroicon-o-arrow-uturn-left',
            self::TRANSFER        => 'heroicon-o-arrows-right-left',
            self::PERSONAL        => 'heroicon-o-user',
            self::CARD_SETTLEMENT => 'heroicon-o-credit-card',
            self::RECEIVABLE      => 'heroicon-o-banknotes',
            self::CAPITAL_IN      => 'heroicon-o-building-library',
        };
    }

    /**
     * Retorna apenas as naturezas compatíveis com o sentido da movimentação (Entrada vs Saída).
     *
     * @return array<string, string>
     */
    public static function optionsForDirection(bool $isCredit): array
    {
        $cases = $isCredit
            ? [self::CARD_SETTLEMENT, self::RECEIVABLE, self::REFUND, self::TRANSFER, self::CAPITAL_IN]
            : [self::EXPENSE, self::RECURRING, self::PAYROLL, self::REFUND, self::TRANSFER, self::PERSONAL];

        return collect($cases)
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->all();
    }
}