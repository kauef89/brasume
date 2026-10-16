<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\BankAccount;
use App\Services\Financial\PaymentGatewayManager;

class PaymentMethodForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make("Configuração de Recebimento")
                ->schema([
                    // --- LINHA 1: NOME, DESTINO E COMPORTAMENTO ---
                    Grid::make(3)->schema([
                        TextInput::make("name")
                            ->label("Nome da Forma de Pagamento")
                            ->required()
                            ->columnSpan(1),

                        Select::make("destination_account_id")
                            ->label("Conta de Destino (Onde o dinheiro cai?)")
                            ->options(fn() => BankAccount::pluck("name", "id"))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),

                        Select::make("engine_type")
                            ->label("Comportamento do Sistema")
                            ->options([
                                "money" => "Dinheiro Físico (Abre Gaveta)",
                                "manual" => "Baixa Manual (Maquininha/Cartões)",
                                "bank_api" => "Integração Bancária (API)",
                                "loss" => "Perda / Cortesia",
                                "internal" => "Consumo Interno",
                                "internal_loyalty" => "Cashback/Fidelidade",
                            ])
                            ->live()
                            ->required()
                            ->columnSpan(1),

                        Select::make("gateway_driver")
                            ->label("Integração (Qual API usar?)")
                            ->options(PaymentGatewayManager::getAvailableGateways())
                            ->visible(fn(Get $get) => $get("engine_type") === 'bank_api')
                            ->live()
                            ->required(fn(Get $get) => $get("engine_type") === 'bank_api')
                            ->columnSpanFull(), // Ocupa a linha toda se aparecer

                        Placeholder::make('integration_status')
                            ->label('Status da Integração')
                            ->visible(fn(Get $get) => $get("engine_type") === 'bank_api' && filled($get("gateway_driver")))
                            ->content(fn(Get $get) => PaymentGatewayManager::getCredentialStatusHtml($get("gateway_driver")))
                            ->columnSpanFull(),
                    ]),

                    // --- LINHA 2: CLASSIFICAÇÃO E FISCAL ---
                    Grid::make(2)->schema([
                        TextInput::make("category")
                            ->label("Categoria de Agrupamento")
                            ->placeholder("Ex: Manual, Perdas")
                            ->columnSpan(1),

                        Select::make("fiscal_code")
                            ->label("Código Fiscal (NFC-e)")
                            ->options([
                                '01' => 'Dinheiro (01)',
                                '04' => 'Cartão de Débito (04)',
                                '03' => 'Cartão de Crédito (03)',
                                '17' => 'PIX (17)',
                                '15' => 'Boleto Bancário (15)',
                                '99' => 'Outros (99)',
                            ])
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText("Este código será enviado à SEFAZ na emissão da nota.")
                            ->placeholder("Selecione o código SEFAZ")
                            ->columnSpan(1),
                    ]),
                ])
                ->columnSpanFull(), // 🚀 Força a secção a ocupar 100% da largura, empurrando a próxima para baixo

                Section::make("Regras de Exibição")->schema([
                                Grid::make(3)->schema([
                                    Toggle::make("show_on_pos")
                                        ->label("Mostrar no PDV (Caixa)?")
                                        ->default(true)
                                        ->inline(false),

                                    Toggle::make("show_on_delivery")
                                        ->label("Mostrar no Delivery?")
                                        ->default(true)
                                        ->inline(false),

                                    Toggle::make("is_active")
                                        ->label("Status (Ativo?)")
                                        ->default(true)
                                        ->inline(false),
                                ]),
                            ]),
                        ];
                    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
