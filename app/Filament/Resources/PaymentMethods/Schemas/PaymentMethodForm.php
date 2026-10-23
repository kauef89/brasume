<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use App\Enums\PaymentEngineType;
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
                    Grid::make(3)->schema([
                        TextInput::make("name")
                            ->label("Nome da Forma de Pagamento")
                            ->placeholder("Ex: Cartão Débito Balcão, Pix Maquineta")
                            ->required()
                            ->columnSpan(1),

                        Select::make("destination_account_id")
                            ->label("Conta de Liquidação / Destino")
                            ->options(fn() => BankAccount::pluck("name", "id"))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),

                        Select::make("engine_type")
                            ->label("Motor de Captura (Comportamento)")
                            ->options(PaymentEngineType::class)
                            ->live()
                            ->required()
                            ->native(false)
                            ->columnSpan(1),

                        Select::make("gateway_driver")
                            ->label("Integração (Qual API bancária acionar?)")
                            ->options(PaymentGatewayManager::getAvailableGateways())
                            ->visible(fn(Get $get) => $get("engine_type") === PaymentEngineType::BANK_API->value || $get("engine_type") === 'bank_api')
                            ->live()
                            ->required(fn(Get $get) => $get("engine_type") === PaymentEngineType::BANK_API->value || $get("engine_type") === 'bank_api')
                            ->columnSpanFull(),

                        Placeholder::make('integration_status')
                            ->label('Estado da Conexão API')
                            ->visible(fn(Get $get) => ($get("engine_type") === PaymentEngineType::BANK_API->value || $get("engine_type") === 'bank_api') && filled($get("gateway_driver")))
                            ->content(fn(Get $get) => PaymentGatewayManager::getCredentialStatusHtml($get("gateway_driver")))
                            ->columnSpanFull(),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make("category")
                            ->label("Categoria de Agrupamento")
                            ->placeholder("Ex: Cartões, Pix, Perdas")
                            ->columnSpan(1),

                        Select::make("fiscal_code")
                            ->label("Código Fiscal (NFC-e)")
                            ->options([
                                '01' => 'Dinheiro (01)',
                                '04' => 'Cartão de Débito (04)',
                                '03' => 'Cartão de Crédito (03)',
                                '17' => 'PIX (17)',
                                '10' => 'Vale Alimentação / Refeição (10)',
                                '15' => 'Boleto Bancário (15)',
                                '99' => 'Outros / Ajustes (99)',
                            ])
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText("Código enviado na tag <tPag> da NFC-e.")
                            ->placeholder("Selecione o código SEFAZ")
                            ->columnSpan(1),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make("Regras Operacionais e Emissão")
                ->schema([
                    Grid::make(4)->schema([
                        Toggle::make("show_on_pos")
                            ->label("Disponível no PDV?")
                            ->default(true)
                            ->inline(false),

                        Toggle::make("show_on_delivery")
                            ->label("Disponível no Delivery?")
                            ->default(true)
                            ->inline(false),

                        Toggle::make("auto_fiscal_emission")
                            ->label("Emissão Fiscal Auto?")
                            ->helperText("Gera NFC-e ao concluir o pedido")
                            ->default(true)
                            ->inline(false),

                        Toggle::make("is_active")
                            ->label("Ativo?")
                            ->default(true)
                            ->inline(false),
                    ]),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
