<?php

namespace App\Filament\Pages\Settings\Schemas\Partials;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

class FiscalSchema
{
    public static function make(): array
    {
        return [
            Section::make("Módulos Ativos do Sistema")
                ->description("Ative ou desative funcionalidades principais para esta loja.")
                ->schema([
                    Toggle::make("is_fiscal_emitter")
                        ->label("Emissor de Nota Fiscal (NFC-e / NF-e)")
                        ->helperText("Se ativado, bloquearemos a edição manual de CNPJ e Razão Social. Anexe o Certificado Digital A1 na aba de Identificação para liberá-los.")
                        ->live()
                        ->default(false),
                ]),

            Section::make("Enquadramento Fiscal e Impostos")
                ->visible(fn(Get $get) => (bool) $get("is_fiscal_emitter"))
                ->schema([
                    Select::make("tax_regime")->label("Regime Tributário")->options([
                        "mei" => "MEI", "simples" => "Simples Nacional", "presumido" => "Lucro Presumido", "real" => "Lucro Real"
                    ])->default("mei")->live()->required()->columnSpan(1),
                    TextInput::make("simples_rate")->label("Alíquota do Simples (%)")->numeric()->default(0.0)->suffix("%")->visible(fn(Get $get) => $get("tax_regime") === "simples")->columnSpan(1),
                    TextInput::make("icms_rate")->label("Alíquota ICMS (%)")->numeric()->default(0.0)->suffix("%")->hidden()->columnSpan(1),
                ])->columns(3),

            Section::make("Configurações de Emissão (NFC-e)")
                ->visible(fn(Get $get) => (bool) $get("is_fiscal_emitter"))
                ->schema([
                    Select::make("environment")
                        ->label("Ambiente da Sefaz")
                        ->options([
                            "2" => "Homologação (Testes)",
                            "1" => "Produção (Validade Jurídica)"
                        ])
                        ->default("2")
                        ->live()
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make("csc_id")
                        ->label("ID do CSC (Produção)")
                        ->visible(fn(Get $get) => ($get("environment") ?? "2") == "1")
                        ->required(fn(Get $get) => ($get("environment") ?? "2") == "1"),

                    TextInput::make("csc_code")
                        ->label("Código CSC Alfanumérico (Produção)")
                        ->password()
                        ->revealable()
                        ->visible(fn(Get $get) => ($get("environment") ?? "2") == "1")
                        ->required(fn(Get $get) => ($get("environment") ?? "2") == "1"),

                    TextInput::make("csc_id_homologation")
                        ->label("ID do CSC (Homologação)")
                        ->visible(fn(Get $get) => ($get("environment") ?? "2") == "2")
                        ->required(fn(Get $get) => ($get("environment") ?? "2") == "2"),

                    TextInput::make("csc_code_homologation")
                        ->label("Código CSC Alfanumérico (Homologação)")
                        ->password()
                        ->revealable()
                        ->visible(fn(Get $get) => ($get("environment") ?? "2") == "2")
                        ->required(fn(Get $get) => ($get("environment") ?? "2") == "2"),

                    TextInput::make("nfce_series")
                        ->label("Série da NFC-e")
                        ->default('1')
                        ->numeric()
                        ->required(),

                    TextInput::make("last_nfce_number")
                        ->label("Último Número Emitido")
                        ->default(0)
                        ->numeric()
                        ->required()
                        ->helperText("Caso esteja a vir de outro sistema, defina a numeração sequencial."),
                ])->columns(2),
        ];
    }
}
