<?php

namespace App\Filament\Pages\Settings\Schemas\Partials;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\TextInput;

class HrSchema
{
    public static function make(): array
    {
        return [
            Section::make("Recursos Humanos e Benefícios")
                ->icon("heroicon-o-users")
                ->description("Configure as regras de Vale-Alimentação (cortesia) e Consumo Interno (desconto em folha).")
                ->schema([
                    
                    Grid::make(2)->schema([
                        // 🚀 FLUXO 1: VALE-ALIMENTAÇÃO (CORTESIA)
                        Toggle::make('has_food_allowance')
                            ->label('Oferecer Vale-Alimentação (Cortesia)')
                            ->helperText('A empresa concede um valor subsidiado. O que for consumido neste limite NÃO é descontado do colaborador.')
                            ->default(false)
                            ->live(),

                        // 🚀 FLUXO 2: FIADO (DESCONTO EM FOLHA)
                        Toggle::make('allow_employee_tab')
                            ->label('Permitir Fiado / Desconto em Folha')
                            ->helperText('Permite que o colaborador consuma na loja e o valor seja acumulado para desconto na diária ou folha de pagamento.')
                            ->default(true)
                            ->live(),
                    ]),

                    // REGRAS DO VALE-ALIMENTAÇÃO (Só exibe se o fluxo 1 estiver ligado)
                    Grid::make(4)
                        ->visible(fn(Get $get) => $get('has_food_allowance'))
                        ->schema([
                            Toggle::make('self_service_enabled')
                                ->label('Autoatendimento (Self-Service)')
                                ->helperText('Colaborador pode montar e pedir no app.')
                                ->default(false)
                                ->inline(false)
                                ->columnSpan(1),

                            Radio::make('allowance_frequency')
                                ->label('Frequência da Cortesia')
                                ->options([
                                    'daily' => 'Diário',
                                    'monthly' => 'Mensal',
                                ])
                                ->default('daily')
                                ->required()
                                ->columnSpan(1),

                            Radio::make('allowance_scope')
                                ->label('Regra de Distribuição')
                                ->options([
                                    'global' => 'Valor Fixo Global',
                                    'individual' => 'Valor Individual',
                                ])
                                ->default('global')
                                ->live()
                                ->required()
                                ->columnSpan(1),

                            TextInput::make('global_allowance_amount')
                                ->label('Valor Global Diário/Mensal')
                                ->numeric()
                                ->prefix('R$')
                                ->required(fn (Get $get) => $get('allowance_scope') === 'global')
                                ->visible(fn (Get $get) => $get('allowance_scope') === 'global')
                                ->columnSpan(1),
                        ])
                ])
        ];
    }
}