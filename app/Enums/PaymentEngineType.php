<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentEngineType: string implements HasLabel, HasIcon, HasColor
{
    // Dinheiro físico e espécies
    case MONEY            = 'money';

    // Terminais e Cartões (Hardware / Adquirente)
    case POS_MANUAL       = 'manual';          // Maquininha avulsa com digitação manual
    case SMART_POS        = 'smart_pos';       // SmartPOS integrada (APOS SiTef / Android)
    case TEF              = 'tef';             // Pinpad USB ligado ao PDV de Balcão

    // Pix e Transações Instantâneas
    case BANK_API         = 'bank_api';        // Pix Dinâmico via API com Webhook
    case PIX_STATIC       = 'pix_static';      // Pix por Chave / Placa de Balcão (conferência visual)
    case PIX_MACHINE      = 'pix_machine';     // Pix QR Code gerado na tela da maquininha (liquidado na adquirente)

    // Convênios, Crédito da Casa e Ajustes
    case CUSTOMER_CREDIT  = 'customer_credit'; // Fiado / Convênio (Conta a Receber)
    case INTERNAL         = 'internal';         // Vale-Refeição / Consumo de Colaborador (RH Ledger)
    case INTERNAL_LOYALTY = 'internal_loyalty'; // Resgate de Cashback / Programa de Pontos
    case LOSS             = 'loss';             // Cortesia, Degustação, Bonificação ou Descarte

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MONEY            => 'Dinheiro em Espécie (Gera Troco / Gaveta)',
            self::POS_MANUAL       => 'Maquininha POS Avulsa (Digitação Manual)',
            self::SMART_POS        => 'SmartPOS Integrada (APOS SiTef / Android)',
            self::TEF              => 'TEF Balcão (Pinpad Integrado)',
            self::BANK_API         => 'Pix Dinâmico Automático (API Bancária / Webhook)',
            self::PIX_STATIC       => 'Pix Chave / Balcão (Conferência Visual)',
            self::PIX_MACHINE      => 'Pix na Maquininha (QR Code da Credenciadora)',
            self::CUSTOMER_CREDIT  => 'Fiado / Convênio (Conta a Receber)',
            self::INTERNAL         => 'Consumo de Colaborador (Vale-Refeição RH)',
            self::INTERNAL_LOYALTY => 'Fidelidade / Cashback',
            self::LOSS             => 'Perda / Cortesia / Degustação',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::MONEY            => 'heroicon-o-banknotes',
            self::POS_MANUAL       => 'heroicon-o-device-phone-mobile',
            self::SMART_POS        => 'heroicon-o-cpu-chip',
            self::TEF              => 'heroicon-o-calculator',
            self::BANK_API         => 'heroicon-o-bolt',
            self::PIX_STATIC       => 'heroicon-o-qr-code',
            self::PIX_MACHINE      => 'heroicon-o-receipt-percent',
            self::CUSTOMER_CREDIT  => 'heroicon-o-book-open',
            self::INTERNAL         => 'heroicon-o-user-group',
            self::INTERNAL_LOYALTY => 'heroicon-o-gift',
            self::LOSS             => 'heroicon-o-hand-raised',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MONEY            => 'success',
            self::POS_MANUAL       => 'gray',
            self::SMART_POS,
            self::TEF              => 'primary',
            self::BANK_API         => 'warning',
            self::PIX_STATIC,
            self::PIX_MACHINE      => 'info',
            self::CUSTOMER_CREDIT  => 'danger',
            self::INTERNAL         => 'purple',
            self::INTERNAL_LOYALTY => 'amber',
            self::LOSS             => 'rose',
        };
    }

    public function isCash(): bool
    {
        return $this === self::MONEY;
    }

    public function isCardOrMachine(): bool
    {
        return in_array($this, [self::POS_MANUAL, self::SMART_POS, self::TEF, self::PIX_MACHINE]);
    }

    public function isDeductibleOrInternal(): bool
    {
        return in_array($this, [self::INTERNAL, self::INTERNAL_LOYALTY, self::LOSS]);
    }
}
