<?php

namespace App\Filament\Pages\Settings\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use App\Filament\Pages\Settings\Schemas\Partials\IdentificationSchema;
use App\Filament\Pages\Settings\Schemas\Partials\PosSchema;
use App\Filament\Pages\Settings\Schemas\Partials\SecuritySchema;
use App\Filament\Pages\Settings\Schemas\Partials\FiscalSchema;
use App\Filament\Pages\Settings\Schemas\Partials\FinancialSchema;
use App\Filament\Pages\Settings\Schemas\Partials\MarketingSchema;
use App\Filament\Pages\Settings\Schemas\Partials\BusinessHoursSchema;
use App\Filament\Pages\Settings\Schemas\Partials\HrSchema;
use App\Filament\Pages\Settings\Schemas\Partials\LogisticsSchema;
use App\Filament\Pages\Settings\Schemas\Partials\VisualIdentitySchema;
use App\Filament\Pages\Settings\Schemas\Partials\SupplyChainSchema;

class CompanySettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Configurações da Empresa')
                    ->tabs([
                        Tabs\Tab::make('Identificação')
                            ->icon('heroicon-o-building-storefront')
                            ->schema(IdentificationSchema::make()),

                        Tabs\Tab::make('Visual do App')
                            ->icon('heroicon-o-photo')
                            ->schema(VisualIdentitySchema::make()),

                        Tabs\Tab::make('Operação e PDV')
                            ->icon('heroicon-o-computer-desktop')
                            ->schema(PosSchema::make()),

                        Tabs\Tab::make('Horários de Atendimento')
                            ->icon('heroicon-o-calendar')
                            ->schema(BusinessHoursSchema::make()),

                        Tabs\Tab::make('Segurança')
                            ->icon('heroicon-o-shield-check')
                            ->schema(SecuritySchema::make()),

                        Tabs\Tab::make('Fiscal e Tributos')
                            ->icon('heroicon-o-document-text')
                            ->schema(FiscalSchema::make()),

                        Tabs\Tab::make('Financeiro')
                            ->icon('heroicon-o-banknotes')
                            ->schema(FinancialSchema::make()),

                        Tabs\Tab::make('Marketing e Fidelidade')
                            ->icon('heroicon-o-gift')
                            ->schema(MarketingSchema::make()),

                        Tabs\Tab::make('Recursos Humanos')
                            ->icon('heroicon-o-user-group')
                            ->schema(HrSchema::make()),

                        Tabs\Tab::make('Logística')
                            ->icon('heroicon-o-truck')
                            ->schema(LogisticsSchema::make()),

                        // 🚀 ABA ADICIONADA: Suprimentos, MRP e Reposição WMS
                        Tabs\Tab::make('Suprimentos e WMS')
                            ->icon('heroicon-o-archive-box')
                            ->schema(SupplyChainSchema::make()),
                    ])
                    ->columnSpanFull()
                    ->contained(false),
            ]);
    }
}
