<?php

namespace App\Filament\Pages\Settings\Schemas\Partials;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

class PosSchema
{
    public static function make(): array
    {
        return [
            Section::make("Cadastro de Clientes e Qualidade de Dados")
                ->schema([
                    Grid::make(5)->schema([
                        Select::make('customer_registration_mode')
                            ->label('Exigência de CPF')
                            ->options([
                                'basic'         => 'Opcional (Todos)',
                                'customer_only' => 'Obrigatório apenas no App do Cliente',
                                'cpf_required'  => 'Obrigatório em Todos (PDV e App)',
                                'serpro_magic'  => 'Integração Serpro (Receita Federal)',
                            ])
                            ->default('basic')
                            ->live()
                            ->columnSpan(1),

                        Toggle::make('require_whatsapp')
                            ->label('Exigir WhatsApp')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('require_birth_date')
                            ->label('Exigir Nasc.')
                            ->default(false)
                            ->disabled(fn(Get $get) => $get('customer_registration_mode') === 'serpro_magic')
                            ->dehydrated()
                            ->live()
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('allow_minors')
                            ->label('Aceitar Menores')
                            ->default(true)
                            ->visible(fn(Get $get) => $get('require_birth_date') || $get('customer_registration_mode') === 'serpro_magic')
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('customer_can_edit_profile')
                            ->label('Permitir Edição?')
                            ->default(true)
                            ->disabled(fn(Get $get) => $get('customer_registration_mode') === 'serpro_magic')
                            ->dehydrated()
                            ->inline(false)
                            ->columnSpan(1),

                        Select::make('address_lookup_mode')
                            ->label('Modo de Busca de Endereço')
                            ->options([
                                'cep'    => 'Por CEP (Cidades Grandes / BrasilAPI)',
                                'street' => 'Por Logradouro (Restrito / Tabela Local)'
                            ])
                            ->default('cep')
                            ->required()
                            ->columnSpan(1),
                    ]),

                    Group::make()
                        ->visible(fn(Get $get) => $get('customer_registration_mode') === 'serpro_magic')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('serpro_client_id')
                                    ->label('Consumer Key (Serpro)')
                                    ->required()
                                    ->columnSpan(1),

                                TextInput::make('serpro_client_secret')
                                    ->label('Consumer Secret (Serpro)')
                                    ->password()
                                    ->revealable()
                                    ->required()
                                    ->columnSpan(1),
                            ]),
                        ]),
                ]),

            Section::make("Regras de Atendimento")->schema([
                Grid::make(4)->schema([
                    Toggle::make("has_service_tax")
                        ->label("Cobrar Taxa de Serviço")
                        ->inline(false)
                        ->live()
                        ->columnSpan(1),

                    TextInput::make("service_tax_percentage")
                        ->label("Taxa de Serviço (%)")
                        ->numeric()
                        ->default(10.0)
                        ->suffix("%")
                        ->visible(fn(Get $get) => (bool) $get("has_service_tax"))
                        ->required(fn(Get $get) => (bool) $get("has_service_tax"))
                        ->columnSpan(1),

                    Select::make('predictive_modifiers_mode')
                        ->label('Preditivo de Sabores (IA)')
                        ->helperText('Destaca combinações baseadas no histórico.')
                        ->options([
                            'all'      => 'Cliente e Garçom',
                            'customer' => 'Apenas Cliente',
                            'waiter'   => 'Apenas Garçom',
                            'disabled' => 'Não usar',
                        ])
                        ->default('disabled')
                        ->required()
                        ->columnSpan(1),

                    Select::make('out_of_stock_mode')
                        ->label('Itens Esgotados')
                        ->options([
                            'hide'            => 'Ocultar do Cardápio',
                            'disable_bottom'  => 'Mostrar no fim (Desabilitado)',
                            'disable_inplace' => 'Mostrar no lugar (Desabilitado)'
                        ])
                        ->default('disable_bottom')
                        ->required()
                        ->columnSpan(1),
                ]),

                Group::make()
                    ->visible(fn(Get $get) => (bool) $get('has_service_tax'))
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('service_tax_table')
                                ->label('Cobrar em Mesa (Salão)')
                                ->helperText('Aplica a taxa nas comandas de mesa.')
                                ->default(true)
                                ->inline(false)
                                ->columnSpan(1),

                            Toggle::make('service_tax_counter')
                                ->label('Cobrar no Balcão')
                                ->helperText('Aplica a taxa em pedidos take-away/retirada.')
                                ->default(false)
                                ->inline(false)
                                ->columnSpan(1),

                            Toggle::make('service_tax_delivery')
                                ->label('Cobrar no Delivery')
                                ->helperText('Aplica a taxa em pedidos de tele-entrega.')
                                ->default(false)
                                ->inline(false)
                                ->columnSpan(1),
                        ]),
                    ]),

                Grid::make(3)->schema([
                    Toggle::make('has_max_change_limit')
                        ->label('Limitar Troco (Delivery)')
                        ->helperText('Restringe o troco levado pelo motoboy.')
                        ->default(false)
                        ->inline(false)
                        ->live()
                        ->columnSpan(1),

                    TextInput::make('max_change_amount')
                        ->label('Troco Máximo Aceito')
                        ->numeric()
                        ->prefix('R$')
                        ->placeholder('50,00')
                        ->helperText('Valor máximo da diferença devolvida.')
                        ->visible(fn(Get $get) => (bool) $get('has_max_change_limit'))
                        ->required(fn(Get $get) => (bool) $get('has_max_change_limit'))
                        ->columnSpan(1),

                    Toggle::make('customer_can_append_orders')
                        ->label('Adicionais no Pedido')
                        ->helperText('Permite que clientes adicionem produtos a um pedido já em preparo.')
                        ->default(true)
                        ->inline(false)
                        ->columnSpan(1),
                ]),
            ]),

            Section::make("Impressão, Comprovantes e Cozinha")
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('checkout_print_mode')
                            ->label('Ação Pós-Pagamento (Caixa)')
                            ->options(function (Get $get) {
                                $options = [
                                    'ask'        => 'Sempre Perguntar (Modal)',
                                    'non_fiscal' => 'Imprimir Recibo Simples',
                                ];

                                if ($get('is_fiscal_emitter')) {
                                    $options['fiscal'] = 'Emitir e Imprimir NFC-e';
                                    $options['both']   = 'Imprimir Ambos';
                                }

                                return $options;
                            })
                            ->default('ask')
                            ->required()
                            ->columnSpan(1),

                        Select::make('kitchen_print_mode')
                            ->label('Agrupamento na Cozinha')
                            ->options([
                                'station' => 'Por Praça (Papéis por categoria)',
                                'item'    => 'Por Item (Um papel por linha)',
                                'order'   => 'Expedição (Tudo num papel)'
                            ])
                            ->default('station')
                            ->required()
                            ->columnSpan(1),

                        Select::make('kds_access_mode')
                            ->label('Acesso ao KDS')
                            ->options([
                                'role'       => 'Acesso Global',
                                'individual' => 'Restrito por Praça'
                            ])
                            ->default('role')
                            ->required()
                            ->columnSpan(1),

                        Select::make('kds_panel_mode')
                            ->label('Painéis KDS Ativos')
                            ->options([
                                'all'      => 'Cozinha e Copa/Bar',
                                'kitchen'  => 'Apenas Cozinha (Geral)',
                                'bar'      => 'Apenas Copa/Bar (App Garçom)',
                                'disabled' => 'Não Utilizar',
                            ])
                            ->default('all')
                            ->live()
                            ->required()
                            ->columnSpan(1),

                        Select::make('bar_kds_routing_mode')
                            ->label('Distribuição de Bebidas (Bar)')
                            ->options([
                                'shared' => 'Lista Compartilhada (Qualquer um faz)',
                                'strict' => 'Dono do Pedido (Garçom faz as próprias bebidas)'
                            ])
                            ->default('shared')
                            ->visible(fn(Get $get) => in_array($get('kds_panel_mode'), ['all', 'bar']))
                            ->required()
                            ->columnSpan(1),

                        // 🍸 NOVA REGRA: Suprime impressão de comandas de bar para mesas
                        Toggle::make('suppress_bar_print_tables')
                            ->label('Não imprimir Bar em Mesas')
                            ->helperText('Itens de bar/copa em mesas não geram papel, operando apenas pelo KDS.')
                            ->default(false)
                            ->visible(fn(Get $get) => in_array($get('kds_panel_mode'), ['all', 'bar']))
                            ->inline(false)
                            ->columnSpan(1),
                    ]),

                    Grid::make(2)->schema([
                        Toggle::make('kitchen_separate_multiples')
                            ->label('Separar Múltiplos na Cozinha (Ex: 3x Cerveja = 3 papéis)')
                            ->default(false)
                            ->inline(false)
                            ->columnSpan(2),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('receipt_footer_text')
                            ->label('Mensagem de Rodapé no Recibo')
                            ->placeholder('Ex: Obrigado! Volte sempre.')
                            ->helperText('Máximo de 42 caracteres para caber perfeitamente na largura do papel.')
                            ->maxLength(42)
                            ->columnSpan(1),

                        TextInput::make('receipt_promo_qrcode')
                            ->label('URL do QR Code Promocional')
                            ->placeholder('Ex: https://g.page/r/sua-empresa/review')
                            ->url()
                            ->maxLength(255)
                            ->columnSpan(1),
                    ]),
                ]),

            Section::make("Gestão de Caixa e Turnos")
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make('require_shift_attendance')
                            ->label('Exigir Escala')
                            ->default(false)
                            ->inline(false)
                            ->columnSpan(1),

                        Select::make('cash_management_mode')
                            ->label('Gestão')
                            ->options([
                                'owner'    => 'Modo Dono',
                                'employee' => 'Modo Funcionário (Cega)'
                            ])
                            ->default('owner')
                            ->live()
                            ->required()
                            ->columnSpan(1),

                        Select::make('cash_opening_mode')
                            ->label('Contagem')
                            ->options([
                                'total'    => 'Valor Fechado',
                                'detailed' => 'Detalhada'
                            ])
                            ->default('total')
                            ->required()
                            ->columnSpan(1),

                        Select::make('carry_over_mode')
                            ->label('Fundo de Troco')
                            ->options([
                                'none'       => 'Zerar o caixa',
                                'coins_only' => 'Manter moedas',
                                'total'      => 'Manter tudo'
                            ])
                            ->default('none')
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('cash_difference_tolerance')
                            ->label('Tolerância Diferença (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(5.00)
                            ->visible(fn(Get $get) => $get('cash_management_mode') === 'employee')
                            ->columnSpan(1),

                        Select::make('default_safe_account_id')
                            ->label('Gaveta / Cofre Padrão')
                            ->options(fn() => \App\Models\BankAccount::where('type', 'vault')->pluck('name', 'id'))
                            ->helperText('Onde as aberturas do caixa injetam dinheiro.')
                            ->columnSpan(1),
                    ]),
                ]),
        ];
    }
}
