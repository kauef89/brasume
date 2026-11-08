<?php

namespace App\Filament\Resources\BankAccounts\Schemas;

use App\Enums\BankAccountType;
use App\Support\BankList;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\Rules\Unique;
use Filament\Schemas\Schema;

class BankAccountForm
{
    protected static function isBankingAccount($type): bool
    {
        $val = $type instanceof \BackedEnum ? $type->value : (string) $type;
        return in_array($val, ['checking', 'savings', 'Conta Corrente', 'Conta Poupança']);
    }

    public static function getFormSchema(): array
    {
        return [
            Tabs::make("Tabs")
                ->tabs([
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
                                ->visible(fn(Get $get) => ($get("type") instanceof \BackedEnum ? $get("type")->value : $get("type")) !== "vault")
                                ->columnSpan(1),

                            TextInput::make("initial_balance")
                                ->label(fn(Get $get) => ($get("type") instanceof \BackedEnum ? $get("type")->value : $get("type")) === 'credit_card' ? 'Limite do Cartão' : 'Saldo Inicial')
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

                        Section::make("Configurações do Cartão de Crédito")
                            ->visible(fn(Get $get) => ($get("type") instanceof \BackedEnum ? $get("type")->value : $get("type")) === "credit_card")
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make("credit_limit")
                                        ->label("Limite Total do Cartão")
                                        ->numeric()
                                        ->prefix("R$")
                                        ->placeholder("Ex: 10000.00"),
                                    TextInput::make("closing_day")
                                        ->label("Dia de Fecho (Melhor Dia)")
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(31)
                                        ->placeholder("Ex: 3"),
                                    TextInput::make("due_day")
                                        ->label("Dia de Vencimento da Fatura")
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(31)
                                        ->placeholder("Ex: 10"),
                                ]),
                            ]),

                        Section::make("Dados Bancários & Convênio CNAB 240")
                            ->visible(fn(Get $get) => self::isBankingAccount($get("type")))
                            ->schema([
                                Grid::make(4)->schema([
                                    TextInput::make("agency")
                                        ->label("Agência / Cooperativa")
                                        ->placeholder("Ex: 2602")
                                        ->maxLength(5)
                                        ->required(),

                                    TextInput::make("agency_digit")
                                        ->label("Dígito Agência")
                                        ->placeholder("Ex: 0")
                                        ->maxLength(2)
                                        ->helperText("Deixe em branco se a agência não possuir dígito."),

                                    TextInput::make("account_number")
                                        ->label("Conta Corrente")
                                        ->placeholder("Ex: 38777")
                                        ->maxLength(12)
                                        ->required(),

                                    TextInput::make("account_digit")
                                        ->label("Dígito Conta")
                                        ->placeholder("Ex: 0")
                                        ->maxLength(2)
                                        ->required(),
                                ]),

                                Grid::make(2)->schema([
                                    TextInput::make('agreement_number')
                                        ->label('Código do Convênio (CNAB 240)')
                                        ->placeholder('Informe o código alfanumérico fornecido pelo banco')
                                        ->maxLength(20)
                                        ->helperText('Obrigatório para emissão de Folha (Sicredi / Sicoob).'),

                                    TextInput::make('pix_key')
                                        ->label('Chave PIX da Conta')
                                        ->placeholder('Ex: CNPJ ou E-mail da conta'),
                                ]),
                            ]),
                    ]),

                    Tabs\Tab::make("Taxas e Adquirentes")
                        ->visible(fn(Get $get) => self::isBankingAccount($get("type")))
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
                                        "none"      => "Não Antecipar (Receber no prazo original)",
                                        "automatic" => "Antecipação Automática (Todo dia)",
                                        "scheduled" => "Agendada (Dias específicos da semana)",
                                    ])
                                    ->live()
                                    ->default("none"),

                                CheckboxList::make("anticipation_days")
                                    ->label("Dias da semana autorizados")
                                    ->options(['monday' => 'Seg.', 'tuesday' => 'Ter.', 'wednesday' => 'Qua.', 'thursday' => 'Qui.', 'friday' => 'Sex.'])
                                    ->columns(5)
                                    ->visible(fn(Get $get) => ($get("anticipation_strategy") instanceof \BackedEnum ? $get("anticipation_strategy")->value : $get("anticipation_strategy")) === "scheduled"),
                            ]),
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
