<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;
use App\Models\BankAccount;

class PaymentMethodForm
{
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Section::make(
                "Configuração de Recebimento",
            )->schema([
                SchemaComponents\Grid::make(3)->schema([
                    FormComponents\TextInput::make("name")
                        ->label("Nome da Forma de Pagamento")
                        ->required()
                        ->columnSpan(1),

                    FormComponents\Select::make("destination_account_id")
                        ->label("Conta de Destino (Onde o dinheiro cai?)")
                        ->options(fn() => BankAccount::pluck("name", "id"))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(2),

                    FormComponents\TextInput::make("category")
                        ->label("Categoria de Agrupamento")
                        ->placeholder("Ex: Manual, Perdas"),

                    FormComponents\Select::make("engine_type")
                        ->label("Comportamento do Sistema")
                        ->options([
                            "money" => "Dinheiro Físico (Abre Gaveta)",
                            "manual" => "Baixa Manual (Maquininha/Cartões)",
                            "pix_sicredi" => "Integração PIX (Sicredi API)",
                            "loss" => "Perda / Cortesia",
                            "internal_consumption" => "Consumo Interno",
                        ])
                        ->required(),

                    FormComponents\TextInput::make("fiscal_code")
                        ->label("Código Fiscal (NFC-e)")
                        ->placeholder("Ex: 01, 03, 04, 17"),
                ]),
            ]),

            SchemaComponents\Section::make("Regras de Exibição")->schema([
                SchemaComponents\Grid::make(3)->schema([
                    FormComponents\Toggle::make("show_on_pos")
                        ->label("Mostrar no PDV (Caixa)?")
                        ->default(true)
                        ->inline(false),

                    FormComponents\Toggle::make("show_on_delivery")
                        ->label("Mostrar no Delivery?")
                        ->default(true)
                        ->inline(false),

                    FormComponents\Toggle::make("is_active")
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
