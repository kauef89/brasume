<?php

namespace App\Filament\Pages\Settings\Schemas\Partials;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class SupplyChainSchema
{
    public static function make(): array
    {
        return [
            Section::make('Cadeia de Suprimentos & Produção (MRP)')
                ->description('Configure a inteligência de reposição e produção automática.')
                ->icon('heroicon-o-truck')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('demand_forecast_mode')
                            ->label('Estratégia de Reposição de Estoque')
                            ->options([
                                'manual_par'   => 'Estoque de Segurança Fixo (Par Level)',
                                'auto_history' => 'Inteligência de Histórico de Vendas',
                            ])
                            ->default('manual_par')
                            ->required(),

                        TextInput::make('production_buffer_days')
                            ->label('Margem de Segurança (Dias)')
                            ->helperText('Quantos dias de "gordura" a produção deve garantir.')
                            ->numeric()
                            ->default(1)
                            ->required(),

                        // 🚀 NOVO CAMPO: Modo de Reposição WMS
                        Select::make('wms_restock_mode')
                            ->label('Modo de Reposição WMS (Prateleiras & Salão)')
                            ->options([
                                'free'         => 'Modo Livre (Anotação / Bloco de Notas Local)',
                                'sales_driven' => 'Modo Puxado (Guiado por Vendas do Turno)',
                            ])
                            ->helperText('No Modo Livre, o atendente anota livremente o que falta trazer (salvo localmente). No Modo Puxado, o sistema exige a reposição exata dos itens vendidos no turno.')
                            ->default('free')
                            ->required()
                            ->columnSpanFull(),
                    ])
                ]),
        ];
    }
}
