<?php

namespace App\Filament\Resources\BankAccounts\Schemas;


use App\Enums\BankAccountType;use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Utilities\Get;
use App\Support\BankList;
use Illuminate\Validation\Rules\Unique;

class BankAccountForm
{
    public static function getFormSchema(): array
    {
        return [
            Tabs::make("Tabs")
                ->tabs([
                    // ==========================================
                    // ABA 1: DETALHES DA CONTA
                    // ==========================================
                    Tabs\Tab::make("Detalhes da Conta")->schema([
                        Grid::make(3)->schema([
                            TextInput::make("name")
                                ->unique(ignoreRecord: true, modifyRuleUsing: fn(Unique $rule) => $rule->withoutTrashed())
                                ->label("Nome de Identificação")
                                ->required()
                                ->placeholder("Ex: Sicredi Principal")
                                ->columnSpan(2),

                            Select::make("type")
                                ->label("Tipo de Conta")
                                ->options(BankAccountType::class)
                                ->required()
                                ->live()
                                ->columnSpan(1),

                            Select::make("bank_code")
                                ->label("Banco")
                                ->options(BankList::all())
                                ->searchable()
                                ->preload()
                                ->visible(fn(Get $get) => $get("type") !== "vault")
                                ->columnSpan(1),

                            TextInput::make("agency")
                                ->label("Agência")
                                ->placeholder("Ex: 0001")
                                ->visible(fn(Get $get) => in_array($get("type"), ["checking", "savings"]))
                                ->columnSpan(1),

                            TextInput::make("account_number")
                                ->label("Número da Conta")
                                ->placeholder("Ex: 12345-6")
                                ->visible(fn(Get $get) => in_array($get("type"), ["checking", "savings"]))
                                ->columnSpan(1),

                            TextInput::make("initial_balance")
                                ->label(fn(Get $get) => $get("type") === 'credit_card' ? 'Limite do Cartão' : 'Saldo Inicial')
                                ->numeric()
                                ->prefix('R$')
                                ->default(0)
                                ->columnSpan(1),

                            Toggle::make("is_active")
                                ->label("Conta Ativa?")
                                ->default(true)
                                ->inline(false)
                                ->columnSpan(1),
                        ]),
                    ]),

                    // ==========================================
                    // ABA 2: TAXAS E RECEBIMENTOS
                    // ==========================================
                    Tabs\Tab::make("Taxas e Adquirentes")
                        ->visible(fn(Get $get) => in_array($get("type"), ["checking", "savings"]))
                        ->schema([
                            Section::make("Taxas de Operação")
                                ->description("Percentuais descontados pela maquininha/adquirente")
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextInput::make("debit_fee")->label("Taxa de Débito")->numeric()->default(0)->suffix("%"),
                                        TextInput::make("credit_fee")->label("Taxa de Crédito")->numeric()->default(0)->suffix("%"),
                                        TextInput::make("anticipation_fee")->label("Taxa Antecipação (a.m)")->numeric()->suffix("%"),
                                    ]),
                                ]),

                            Section::make("Prazos de Liquidação")
                                ->description("Dias úteis até o dinheiro cair na conta (Esperado)")
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make("debit_term_days")->label("Prazo Débito (Dias)")->numeric()->default(1),
                                        TextInput::make("credit_term_days")->label("Prazo Crédito (Dias)")->numeric()->default(30),
                                    ]),
                                ]),

                            Section::make("Calendário de Antecipação")->schema([
                                Select::make("anticipation_strategy")
                                    ->label("Estratégia de Antecipação")
                                    ->options([
                                        "none" => "Não Antecipar (Receber no prazo original)",
                                        "automatic" => "Antecipação Automática (Todo dia)",
                                        "scheduled" => "Agendada (Dias específicos da semana)",
                                    ])
                                    ->live()
                                    ->default("none"),

                                CheckboxList::make("anticipation_days")
                                    ->label("Dias da semana autorizados")
                                    ->options(['monday' => 'Seg.', 'tuesday' => 'Ter.', 'wednesday' => 'Qua.', 'thursday' => 'Qui.', 'friday' => 'Sex.'])
                                    ->columns(5)
                                    ->visible(fn(Get $get) => $get("anticipation_strategy") === "scheduled"),
                            ]),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
        {
            return $schema->schema(self::getFormSchema());
        }
}
