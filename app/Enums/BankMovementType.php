<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BankMovementType: string implements HasLabel, HasColor, HasIcon
{
    case PIX_IN              = 'pix_in';
    case PIX_OUT             = 'pix_out';
    case REFUND              = 'refund';
    case CARD_IN             = 'card_in';
    case DEBIT_CARD          = 'debit_card';
    case CREDIT_CARD_PAYMENT = 'credit_card_payment';
    case BOLETO_OUT          = 'boleto_out';
    case TRANSFER            = 'transfer';
    case FIXED_BILL          = 'fixed_bill';
    case TAX_BILL            = 'tax_bill';
    case PAYROLL             = 'payroll';
    case BANK_FEE            = 'bank_fee';
    case ATM_IN              = 'atm_in';
    case ATM_OUT             = 'atm_out';
    case LOAN_IN             = 'loan_in';
    case OTHER               = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PIX_IN              => 'Pix Recebido',
            self::PIX_OUT             => 'Pix Pago',
            self::REFUND              => 'Estorno / Devolução',
            self::CARD_IN             => 'Repasse Cartão',
            self::DEBIT_CARD          => 'Compra Débito',
            self::CREDIT_CARD_PAYMENT => 'Fatura Cartão',
            self::BOLETO_OUT          => 'Boleto',
            self::TRANSFER            => 'Transferência',
            self::FIXED_BILL          => 'Conta Fixa',
            self::TAX_BILL            => 'Imposto',
            self::PAYROLL             => 'Folha Pagto.',
            self::BANK_FEE            => 'Taxa / Encargo',
            self::ATM_IN              => 'Depósito ATM',
            self::ATM_OUT             => 'Saque ATM',
            self::LOAN_IN             => 'Crédito / Giro',
            self::OTHER               => 'Outros',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::PIX_IN, self::CARD_IN, self::ATM_IN, self::LOAN_IN => 'success',
            self::REFUND, self::TAX_BILL, self::CREDIT_CARD_PAYMENT  => 'info',
            self::BANK_FEE                                           => 'danger',
            self::OTHER                                              => 'gray',
            default                                                  => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PIX_IN              => 'heroicon-m-arrow-down-left',
            self::PIX_OUT             => 'heroicon-m-arrow-up-right',
            self::REFUND              => 'heroicon-m-arrow-uturn-left',
            self::CARD_IN             => 'heroicon-m-credit-card',
            self::DEBIT_CARD          => 'heroicon-m-shopping-bag',
            self::CREDIT_CARD_PAYMENT => 'heroicon-m-document-check',
            self::BOLETO_OUT          => 'heroicon-m-qr-code',
            self::TRANSFER            => 'heroicon-m-arrows-right-left',
            self::FIXED_BILL          => 'heroicon-m-arrow-path',
            self::TAX_BILL            => 'heroicon-m-building-library',
            self::PAYROLL             => 'heroicon-m-users',
            self::BANK_FEE            => 'heroicon-m-exclamation-triangle',
            self::ATM_IN              => 'heroicon-m-banknotes',
            self::ATM_OUT             => 'heroicon-m-banknotes',
            self::LOAN_IN             => 'heroicon-m-plus-circle',
            self::OTHER               => 'heroicon-m-ellipsis-horizontal-circle',
        };
    }
}