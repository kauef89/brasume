<?php

namespace App\Filament\Pages\Settings\Schemas\Partials;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

class MarketingSchema
{
    public static function make(): array
    {
        return [
            Section::make("Programa de Fidelidade")
                ->description("Incentive a recompra recompensando os seus clientes mais fiéis.")
                ->schema([
                    Toggle::make('loyalty_is_active')
                        ->label('Ativar Programa de Fidelidade')
                        ->default(false)
                        ->live(),

                    Grid::make(3)
                        ->visible(fn(Get $get) => (bool) $get('loyalty_is_active'))
                        ->schema([
                            Radio::make('loyalty_reward_type')
                                ->label('Tipo de Recompensa')
                                ->options([
                                    'cashback' => 'Cashback (Saldo em R$ para a próxima compra)',
                                    'product'  => 'Cortesia (Trocar pontos por produtos específicos)',
                                ])
                                ->default('cashback')
                                ->live(),

                            Select::make('loyalty_accumulation_rule')
                                ->label('Regra de Acúmulo de Pontos')
                                ->options([
                                    'cart_value'                => 'Por Valor do Pedido (Ex: R$ 1 = 1 Ponto)',
                                    'product_qty_all'           => 'Por Quantidade (Qualquer produto = 1 Ponto)',
                                    'product_qty_specific'      => 'Apenas Produtos Selecionados pontuam',
                                    'product_individual_points' => 'Pontuação Individual (Cada produto tem seu valor)',
                                ])
                                ->default('cart_value')
                                ->live(),
                        ]),

                    Grid::make(4)
                        ->visible(fn(Get $get) => (bool) $get('loyalty_is_active') && in_array($get('loyalty_accumulation_rule'), ['cart_value', 'product_qty_all']))
                        ->schema([
                            TextInput::make('loyalty_points_multiplier')
                                ->label(fn(Get $get) => $get('loyalty_accumulation_rule') === 'cart_value' ? 'Pontos gerados a cada R$ 1,00?' : 'Pontos gerados por Item?')
                                ->numeric()
                                ->default(1)
                                ->required(),
                        ]),

                        Section::make("Regras de Resgate (Cashback)")
                            ->visible(fn(Get $get) => (bool) $get('loyalty_is_active') && $get('loyalty_reward_type') === 'cashback')
                            ->schema([
                                Grid::make(5)->schema([
                                    TextInput::make('loyalty_cashback_conversion_rate')
                                        ->label('Taxa de Conversão')
                                        ->helperText('Ex: A cada 100 pontos = R$ 5,00 de saldo.')
                                        ->numeric()
                                        ->prefix('R$')
                                        ->default(5.00),

                                    TextInput::make('loyalty_min_redeem_value')
                                        ->label('Consumo Mínimo para Resgate')
                                        ->helperText('O cliente só pode usar o cashback se o pedido passar deste valor.')
                                        ->numeric()
                                        ->prefix('R$')
                                        ->default(30.00),

                                    Select::make('loyalty_max_redeem_type')
                                        ->label('Formato do Limite Máximo')
                                        ->options([
                                            'fixed'      => 'Valor Fixo (R$)',
                                            'percentage' => 'Percentual do Pedido (%)',
                                        ])
                                        ->default('fixed')
                                        ->live(),

                                    TextInput::make('loyalty_max_redeem_value')
                                        ->label('Teto Máximo de Resgate')
                                        ->helperText('Informe 0 ou deixe vazio para permitir resgate sem teto.')
                                        ->numeric()
                                        ->minValue(0)
                                        ->prefix(fn(Get $get) => $get('loyalty_max_redeem_type') === 'percentage' ? null : 'R$')
                                        ->suffix(fn(Get $get) => $get('loyalty_max_redeem_type') === 'percentage' ? '%' : null)
                                        ->nullable(),

                                    TextInput::make('loyalty_points_expiry_days')
                                        ->label('Validade dos Pontos (Expiração)')
                                        ->helperText('Deixe vazio ou 0 para pontos vitalícios (não recomendado).')
                                        ->numeric()
                                        ->minValue(0)
                                        ->suffix('dias')
                                        ->nullable(),
                                ])
                            ]),

                    Section::make("Regras de Resgate (Cortesia)")
                        ->visible(fn(Get $get) => (bool) $get('loyalty_is_active') && $get('loyalty_reward_type') === 'product')
                        ->schema([
                            Placeholder::make('info_cortesia')
                                ->hiddenLabel()
                                ->content(new HtmlString('<div class="p-3 bg-info-50 text-info-700 rounded-xl text-sm"><i class="pi pi-info-circle mr-2"></i> Para definir o custo em pontos de cada prêmio, acesse o cadastro do Produto, vá à aba <b>Preços e Estoque</b> e ative a flag <b>"Prêmio de Fidelidade"</b>.</div>'))
                        ]),
                ]),

            // 🚀 NOVA SEÇÃO DE AUTOMAÇÃO DE CRM
            Section::make("Automação de CRM (Retenção e Churn)")
                ->description("Configure campanhas automáticas para datas especiais e recuperação de clientes inativos.")
                ->icon('heroicon-o-sparkles')
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('auto_birthday_coupon')
                            ->label('Campanha de Aniversário')
                            ->helperText('O sistema gerará e exibirá um cupom exclusivo no app no dia do aniversário do cliente.')
                            ->live()
                            ->default(false)
                            ->columnSpanFull(),

                        FileUpload::make('birthday_banner_path')
                            ->label('Imagem do Banner de Aniversário')
                            ->helperText('Exibido na Home do App. Proporção ideal: 16:9.')
                            ->image()
                            ->directory('marketing/banners')
                            ->disk('public')
                            ->visibility('public')
                            ->visible(fn(Get $get) => $get('auto_birthday_coupon'))
                            ->columnSpanFull(),
                    ]),

                    Grid::make(2)->schema([
                        Toggle::make('auto_churn_coupon')
                            ->label('Campanha de Recuperação (Risco de Fuga)')
                            ->helperText('Gera um cupom se o cliente ficar muito tempo sem comprar.')
                            ->live()
                            ->default(false)
                            ->columnSpanFull(),

                        TextInput::make('churn_days_threshold')
                            ->label('Dias de Ausência')
                            ->helperText('A partir de quantos dias sem pedir o cliente recebe esta campanha?')
                            ->numeric()
                            ->default(30)
                            ->suffix('dias')
                            ->visible(fn(Get $get) => $get('auto_churn_coupon'))
                            ->columnSpan(1),

                        FileUpload::make('churn_banner_path')
                            ->label('Imagem do Banner de Retorno')
                            ->helperText('Exibido na Home do App para atrair o cliente de volta.')
                            ->image()
                            ->directory('marketing/banners')
                            ->disk('public')
                            ->visibility('public')
                            ->visible(fn(Get $get) => $get('auto_churn_coupon'))
                            ->columnSpanFull(),
                    ])
                ]),

            Section::make("Rastreamento e Pixels (Growth)")
                ->description("Conecte as suas ferramentas de tráfego pago para medir conversões e otimizar campanhas.")
                ->icon('heroicon-o-chart-bar')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('meta_pixel_id')
                            ->label('Meta Pixel ID (Facebook/Instagram)')
                            ->placeholder('Ex: 123456789012345')
                            ->columnSpan(1),

                        TextInput::make('meta_capi_token')
                            ->label('Meta Conversions API Token')
                            ->password()
                            ->revealable()
                            ->placeholder('Cole o token de acesso da CAPI aqui')
                            ->columnSpan(1),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('google_pixel_id')
                            ->label('Google Analytics / Ads (Measurement ID)')
                            ->placeholder('Ex: G-XXXXXXXXXX ou AW-XXXXXXXXX')
                            ->columnSpan(1),
                    ])
                ]),

            Section::make('Avaliações e Reputação')
                ->description('Configure para onde os clientes satisfeitos serão direcionados.')
                ->icon('heroicon-o-star')
                ->schema([
                    Toggle::make('request_order_rating')
                        ->label('Solicitar Avaliação')
                        ->helperText('Exibe o widget de avaliação no app para clientes com pedidos recém-entregues.')
                        ->default(true)
                        ->live(),

                    TextInput::make('google_review_link')
                        ->label('Link de Avaliação do Google Meu Negócio')
                        ->placeholder('https://g.page/r/SEU_CODIGO/review')
                        ->url()
                        ->maxLength(255)
                        ->helperText('Fornecido a clientes que avaliam o pedido com 5 estrelas no App.')
                        ->visible(fn(Get $get) => (bool) $get('request_order_rating')),
                ]),

            // 🖨️ NOVA SEÇÃO: MARKETING OFFLINE (IMPRESSORA TÉRMICA)
            Section::make("Marketing Offline (Impressão Térmica)")
                ->description("Estratégias impressas no recibo do pedido para converter clientes do WhatsApp/Balcão em usuários do seu App.")
                ->icon('heroicon-o-printer')
                ->schema([

                    // 1. FLYER DE FIDELIDADE (O2O)
                    Grid::make(3)
                        ->visible(fn(Get $get) => (bool) $get('loyalty_is_active'))
                        ->schema([
                            Toggle::make('print_loyalty_flyer')
                                ->label('Imprimir Flyer de App/Cashback')
                                ->helperText('Avisa o cliente sobre pontos ganhos e exibe o QR Code para baixar o App.')
                                ->live()
                                ->columnSpanFull(),

                            Radio::make('loyalty_flyer_mode')
                                ->label('Modo de Impressão')
                                ->options([
                                    'auto' => 'Automático (Sempre imprime no fim do recibo)',
                                    'manual' => 'Manual (Perguntar ao Caixa/Despachador antes)',
                                ])
                                ->inline()
                                ->default('manual')
                                ->visible(fn(Get $get) => $get('print_loyalty_flyer'))
                                ->columnSpanFull(),
                        ]),

                    // Aviso caso a fidelidade esteja desligada
                    Placeholder::make('loyalty_flyer_warning')
                        ->hiddenLabel()
                        ->content(new HtmlString('<div class="p-3 bg-warning-50 text-warning-700 rounded-xl text-sm"><i class="pi pi-exclamation-triangle mr-2"></i> O Flyer de Fidelidade/Cashback está oculto porque o Programa de Fidelidade está desativado.</div>'))
                        ->visible(fn(Get $get) => !(bool) $get('loyalty_is_active'))
                        ->columnSpanFull(),

                        Placeholder::make('divider')
                            ->hiddenLabel()
                            ->content(new HtmlString('<hr class="my-2 border-gray-200 dark:border-gray-700" />'))
                            ->columnSpanFull(),

                    // 2. FLYER DE GROWTH (PRESENTE / INDICAÇÃO)
                    Grid::make(2)->schema([
                        Toggle::make('print_growth_flyer')
                            ->label('Imprimir Flyer de Presente (Growth)')
                            ->helperText('Gera um cupom descartável para o cliente presentear um amigo na primeira compra.')
                            ->live()
                            ->columnSpanFull(),

                        Radio::make('growth_flyer_mode')
                            ->label('Modo de Impressão')
                            ->options([
                                'auto' => 'Automático (Sempre imprime)',
                                'manual' => 'Manual (Perguntar antes)',
                            ])
                            ->inline()
                            ->default('manual')
                            ->visible(fn(Get $get) => $get('print_growth_flyer'))
                            ->columnSpanFull(),

                        TextInput::make('growth_flyer_value')
                            ->label('Valor do Cupom de Presente')
                            ->helperText('Quantos reais de desconto o amigo vai ganhar?')
                            ->numeric()
                            ->prefix('R$')
                            ->default(10.00)
                            ->visible(fn(Get $get) => $get('print_growth_flyer'))
                            ->columnSpan(1),
                    ]),
                ]),

        ];
    }
}
