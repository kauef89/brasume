<?php

namespace App\Filament\Pages\Settings\Schemas\Partials;

use App\Enums\Weekday;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class BusinessHoursSchema
{
    public static function make(): array
    {
        return [
            Section::make("Regras de Funcionamento e Automação")
                ->icon("heroicon-o-clock")
                ->description("Configure a dependência do caixa financeiro para clientes e para a equipe interna de forma independente.")
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('enforce_cashier_open_customer')
                            ->label('Caixa Obrigatório: Cardápio do Cliente')
                            ->helperText('Se ativo, o Cardápio Digital (Delivery / Retirada) só aceita pedidos se houver um Turno de Caixa financeiro aberto.')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('enforce_cashier_open_team')
                            ->label('Caixa Obrigatório: POS & Garçons (TeamApp)')
                            ->helperText('Se ativo, a equipe só consegue lançar comandas se o caixa estiver formalmente aberto. Deixe desmarcado para permitir pré-atendimento independente.')
                            ->default(false)
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('allow_pre_orders')
                            ->label('Aceitar Pré-venda (Pre-orders)')
                            ->helperText('Permite que os clientes façam pedidos com a loja fechada. O preparo iniciará no horário de abertura programado.')
                            ->default(false)
                            ->live()
                            ->inline(false)
                            ->columnSpan(1),

                        TextInput::make('pre_order_buffer_minutes')
                            ->label('Antecedência de Pré-venda (Minutos)')
                            ->helperText('Quantos minutos ANTES da abertura oficial o sistema começa a aceitar os pedidos?')
                            ->numeric()
                            ->suffix('min')
                            ->visible(fn(Get $get) => (bool) $get('allow_pre_orders'))
                            ->required(fn(Get $get) => (bool) $get('allow_pre_orders'))
                            ->columnSpan(1),
                    ])
                ])->columnSpanFull(),

            Section::make("Matriz de Horários de Operação")
                ->description("Cadastre os turnos de funcionamento. Crie múltiplos turnos para horários quebrados ou canais independentes (Ex: 18h só Delivery, 19h Salão e Delivery).")
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('is_24_hours')
                            ->label('Aberto 24 Horas')
                            ->helperText('A loja opera sem interrupções. Ignora e oculta a matriz de horários.')
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $state ? $set('is_closed_indefinitely', false) : null)
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('is_closed_indefinitely')
                            ->label('Fechado Indeterminadamente')
                            ->helperText('Oculta horários, fecha a loja e bloqueia novos pedidos (Ideal para emergências ou reformas).')
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $state ? $set('is_24_hours', false) : null)
                            ->inline(false)
                            ->columnSpan(1),
                    ])->extraAttributes(['class' => 'mb-4']),

                    Repeater::make('business_hours')
                        ->hidden(fn (Get $get) => $get('is_24_hours') || $get('is_closed_indefinitely'))
                        ->hiddenLabel()
                        ->schema([
                            Select::make('day_of_week')
                                ->label('Dia da Semana')
                                ->options(Weekday::numericOptions())
                                ->required()
                                ->columnSpan(2),

                            TimePicker::make('open_time')
                                ->label('Abertura')
                                ->required()
                                ->seconds(false)
                                ->columnSpan(1),

                            TimePicker::make('close_time')
                                ->label('Fechamento')
                                ->required()
                                ->seconds(false)
                                ->columnSpan(1),

                            CheckboxList::make('active_channels')
                                ->label('Canais Liberados neste Turno')
                                ->options([
                                    'delivery' => 'Delivery',
                                    'counter'  => 'Retirada / Balcão',
                                    'table'    => 'Consumo no Local (Mesas)'
                                ])
                                ->columns(3)
                                ->default(['delivery', 'counter', 'table'])
                                ->columnSpanFull(),
                        ])
                        ->columns(4)
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => isset($state['day_of_week']) ? ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][$state['day_of_week']] . ' (' . ($state['open_time'] ?? '--:--') . ' às ' . ($state['close_time'] ?? '--:--') . ')' : null)
                        ->addActionLabel('Adicionar Novo Turno')
                        ->defaultItems(0)
                ])->columnSpanFull()
        ];
    }
}
