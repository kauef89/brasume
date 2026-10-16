<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderSource: string implements HasLabel, HasIcon
{
    case CUSTOMER_APP = 'customer_app';
    case CUSTOMER_APP_TABLE = 'customer_app_table';
    case POS_APP = 'pos_app';
    case CASHIER = 'cashier';
    case TEAM_APP = 'team_app';
    case KIOSK = 'kiosk';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CUSTOMER_APP => 'App Cliente (Delivery/Retirada)',
            self::CUSTOMER_APP_TABLE => 'App Cliente (Mesa)',
            self::POS_APP => 'PDV / Tablet',
            self::CASHIER => 'Caixa Fixo',
            self::TEAM_APP => 'App Garçom',
            self::KIOSK => 'Totem de Autoatendimento',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::CUSTOMER_APP, self::CUSTOMER_APP_TABLE => 'heroicon-o-device-phone-mobile',
            self::POS_APP, self::CASHIER => 'heroicon-o-computer-desktop',
            self::TEAM_APP => 'heroicon-o-clipboard-document-list',
            self::KIOSK => 'heroicon-o-server',
        };
    }
}
