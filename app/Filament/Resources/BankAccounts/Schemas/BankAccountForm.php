<?php

namespace App\Filament\Resources\BankAccounts\Schemas;

use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;
use Filament\Schemas\Components\Utilities\Get;
use App\Support\BankList;
use Illuminate\Validation\Rules\Unique;

class BankAccountForm
{
    /**
     * @return array<int,SchemaComponents\Tabs>
     */
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Tabs::make("Tabs")
                ->tabs([
                    // ==========================================
                    // ABA 1: DETALHES DA CONTA
                    // ==========================================
                    SchemaComponents\Tabs\Tab::make(
                        "Detalhes da Conta",
                    )->schema([
                        SchemaComponents\Grid::make(3)->schema([
                            FormComponents\TextInput::make("name")
                                ->unique(
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn(
                                        Unique $rule,
                                    ) => $rule->withoutTrashed(),
                                )
                                ->label("Nome de Identificação")
                                ->required()
                                ->placeholder("Ex: Sicredi Principal")
                                ->columnSpan(2),

                            FormComponents\Select::make("type")
                                ->label("Tipo de Conta")
                                ->options([
                                    "vault" => "Cofre (Dinheiro Físico)",
                                    "checking" => "Conta Corrente",
                                    "savings" => "Conta Poupança",
                                ])
                                ->required()
                                ->live()
                                ->columnSpan(1),

                            FormComponents\Select::make("bank_code")
                                ->label("Banco")
                                ->options(BankList::all())
                                ->searchable()
                                ->preload()
                                ->visible(
                                    fn(Get $get) => $get("type") !== "vault",
                                )
                                ->columnSpan(1),

                            FormComponents\TextInput::make("agency")
                                ->label("Agência")
                                ->placeholder("Ex: 0001")
                                ->visible(
                                    fn(Get $get) => $get("type") !== "vault",
                                )
                                ->columnSpan(1),

                            FormComponents\TextInput::make("account_number")
                                ->label("Número da Conta")
                                ->placeholder("Ex: 12345-6")
                                ->visible(
                                    fn(Get $get) => $get("type") !== "vault",
                                )
                                ->columnSpan(1),

                            FormComponents\TextInput::make("initial_balance")
                                ->label("Saldo Inicial")
                                ->numeric()
                                ->prefix('R$')
                                ->default(0)
                                ->columnSpan(1),

                            FormComponents\Toggle::make("is_active")
                                ->label("Conta Ativa?")
                                ->default(true)
                                ->inline(false)
                                ->columnSpan(1),
                        ]),
                    ]),

                    // ==========================================
                    // ABA 2: TAXAS E RECEBIMENTOS DE MAQUININHA
                    // ==========================================
                    SchemaComponents\Tabs\Tab::make("Taxas e Recebimentos")
                        // Oculta esta aba se for Cofre
                        ->visible(
                            fn(Get $get) => in_array($get("type"), [
                                "checking",
                                "savings",
                            ]),
                        )
                        ->schema([
                            SchemaComponents\Section::make("Taxas de Operação")
                                ->description(
                                    "Percentuais descontados pela adquirente/maquininha",
                                )
                                ->schema([
                                    SchemaComponents\Grid::make(3)->schema([
                                        FormComponents\TextInput::make(
                                            "debit_fee",
                                        )
                                            ->label("Taxa de Débito")
                                            ->numeric()
                                            ->default(0)
                                            ->suffix("%"),
                                        FormComponents\TextInput::make(
                                            "credit_fee",
                                        )
                                            ->label("Taxa de Crédito")
                                            ->numeric()
                                            ->default(0)
                                            ->suffix("%"),
                                        FormComponents\TextInput::make(
                                            "anticipation_fee",
                                        )
                                            ->label("Taxa Antecipação (a.m)")
                                            ->numeric()
                                            ->helperText(
                                                "Taxa extra cobrada se antecipar parcelas",
                                            )
                                            ->suffix("%"),
                                    ]),
                                ]),

                            SchemaComponents\Section::make(
                                "Prazos de Liquidação",
                            )
                                ->description(
                                    "Dias úteis até o dinheiro cair na conta",
                                )
                                ->schema([
                                    SchemaComponents\Grid::make(2)->schema([
                                        FormComponents\TextInput::make(
                                            "debit_term_days",
                                        )
                                            ->label("Prazo Débito (Dias)")
                                            ->numeric()
                                            ->default(1),
                                        FormComponents\TextInput::make(
                                            "credit_term_days",
                                        )
                                            ->label("Prazo Crédito (Dias)")
                                            ->numeric()
                                            ->default(30),
                                    ]),
                                ]),

                            SchemaComponents\Section::make(
                                "Calendário de Antecipação",
                            )->schema([
                                FormComponents\Select::make(
                                    "anticipation_strategy",
                                )
                                    ->label("Estratégia de Antecipação")
                                    ->options([
                                        "none" =>
                                            "Não Antecipar (Receber no prazo original)",
                                        "automatic" =>
                                            "Antecipação Automática (Todo dia)",
                                        "scheduled" =>
                                            "Agendada (Dias específicos da semana)",
                                    ])
                                    ->live()
                                    ->default("none"),

                                FormComponents\CheckboxList::make(
                                    "anticipation_days",
                                )
                                    ->label("Dias da semana autorizados")
                                    ->options([
                                        "monday" => "Segunda-feira",
                                        "tuesday" => "Terça-feira",
                                        "wednesday" => "Quarta-feira",
                                        "thursday" => "Quinta-feira",
                                        "friday" => "Sexta-feira",
                                    ])
                                    ->columns(5)
                                    ->visible(
                                        fn(Get $get) => $get(
                                            "anticipation_strategy",
                                        ) === "scheduled",
                                    ),
                            ]),
                        ]),

                    // ==========================================
                    // ABA 3: CARTÕES CORPORATIVOS
                    // ==========================================
                    SchemaComponents\Tabs\Tab::make(
                        "Cartões de Crédito Corporativos",
                    )
                        // Oculta esta aba se for Cofre
                        ->visible(
                            fn(Get $get) => in_array($get("type"), [
                                "checking",
                                "savings",
                            ]),
                        )
                        ->schema([
                            FormComponents\Repeater::make("creditCards")
                                ->relationship("creditCards")
                                ->label("Cartões Vinculados")
                                ->addActionLabel("Adicionar Novo Cartão")
                                ->schema([
                                    SchemaComponents\Grid::make(3)->schema([
                                        FormComponents\TextInput::make("name")
                                            ->label("Apelido / Final do Cartão")
                                            ->required(),
                                        FormComponents\TextInput::make("limit")
                                            ->label("Limite Misto")
                                            ->numeric()
                                            ->prefix('R$'),
                                        FormComponents\Toggle::make("is_active")
                                            ->label("Ativo?")
                                            ->default(true),
                                    ]),
                                ])
                                ->columnSpanFull(),
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
