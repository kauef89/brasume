<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Enums\EmployeeRole;
use App\Enums\EmploymentType;
use App\Enums\PaymentCycle;
use App\Models\CompanySetting;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make("Informações Básicas")
                ->schema([
                    TextInput::make("name")
                        ->label("Nome Completo")
                        ->required(),

                    TextInput::make("cpf")
                        ->label("CPF")
                        ->mask("999.999.999-99")
                        ->maxLength(14),

                    TextInput::make("email")
                        ->email()
                        ->label("E-mail"),

                    TextInput::make("phone")
                        ->label("Telefone/WhatsApp"),

                    // 🚀 FUNÇÃO OPERACIONAL NA LANCHONETE (Fonte Única da Verdade)
                    Select::make("role")
                        ->label("Função Operacional Principal")
                        ->options(EmployeeRole::class)
                        ->enum(EmployeeRole::class)
                        ->default(EmployeeRole::WAITER)
                        ->required()
                        ->helperText("Define o papel principal do colaborador na escala, turnos e aplicativo de equipe."),

                    Select::make("secondary_roles")
                        ->label("Funções Adicionais Habilitadas")
                        ->options(EmployeeRole::class)
                        ->multiple()
                        ->helperText("Outras funções que este colaborador tem permissão para cobrir na escala."),

                    Toggle::make("is_active")
                        ->label("Colaborador Ativo")
                        ->default(true)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make("Contrato & Remuneração")
                ->schema([
                    Select::make("employment_type")
                        ->label("Tipo de Vínculo")
                        ->options(EmploymentType::class)
                        ->enum(EmploymentType::class)
                        ->required()
                        ->live()
                        ->columnSpan(1),

                    TextInput::make("base_remuneration")
                        ->label(function ($get) {
                            $type = $get("employment_type");
                            $val = $type instanceof EmploymentType ? $type->value : (string) $type;

                            return match (true) {
                                str_starts_with($val, 'clt'), $val === 'intern' => 'Salário Base Mensal',
                                $val === 'partner' => 'Pró-Labore Mensal',
                                $val === 'pj' => 'Valor do Contrato Mensal',
                                default => 'Valor por Diária / Turno',
                            };
                        })
                        ->helperText(function ($get) {
                            $type = $get("employment_type");
                            $val = $type instanceof EmploymentType ? $type->value : (string) $type;

                            return match (true) {
                                str_starts_with($val, 'clt'), $val === 'intern', $val === 'partner', $val === 'pj'
                                    => 'Remuneração fixa mensal base para holerite e provisões.',
                                default
                                    => 'Remuneração variável calculada com base nos plantões da escala.',
                            };
                        })
                        ->numeric()
                        ->prefix('R$')
                        ->required()
                        ->columnSpan(1),

                    Select::make("payment_cycle")
                        ->label("Ciclo de Pagamento")
                        ->options(PaymentCycle::class)
                        ->enum(PaymentCycle::class)
                        ->required(),

                    TextInput::make('food_allowance_amount')
                        ->label('Limite do Vale-Alimentação')
                        ->numeric()
                        ->prefix('R$')
                        ->helperText('Defina o valor do vale para este colaborador. O saldo será renovado automaticamente conforme a frequência configurada no RH.')
                        ->visible(function () {
                            $settings = CompanySetting::getCached();
                            return $settings && $settings->has_food_allowance && $settings->allowance_scope === 'individual';
                        }),
                ])
                ->columns(2),

            Section::make("Dados Bancários & Pagamento")
                ->icon("heroicon-o-credit-card")
                ->schema([
                    Select::make("payment_method")
                        ->label("Forma Principal de Pagamento")
                        ->options([
                            "salary_account" => "Conta-Salário Sicredi (Lote CNAB 240)",
                            "pix"            => "Chave PIX",
                            "cash"           => "Dinheiro / Espécie",
                        ])
                        ->default(function ($get) {
                            $type = $get("employment_type");
                            $val = $type instanceof EmploymentType ? $type->value : (string) $type;
                            return str_starts_with($val, 'clt') ? 'salary_account' : 'pix';
                        })
                        ->live()
                        ->required()
                        ->helperText("Define o canal de liquidação para remessas bancárias e holerites.")
                        ->columnSpanFull(),

                    // BLOCO 1: CONTA-SALÁRIO / COOPERATIVA SICREDI (OBRIGATÓRIO PARA CNAB 240)
                    Grid::make(3)
                        ->visible(fn ($get) => $get("payment_method") === "salary_account")
                        ->schema([
                            TextInput::make("bank_agency")
                                ->label("Cooperativa Destino (Agência)")
                                ->placeholder("Ex: 2602")
                                ->maxLength(4)
                                ->required(fn ($get) => $get("payment_method") === "salary_account")
                                ->helperText("Número da cooperativa Sicredi do colaborador."),

                            TextInput::make("bank_account")
                                ->label("Conta Destino")
                                ->placeholder("Ex: 38777")
                                ->maxLength(10)
                                ->required(fn ($get) => $get("payment_method") === "salary_account")
                                ->helperText("Número da conta sem o dígito."),

                            TextInput::make("bank_account_digit")
                                ->label("Dígito (DV)")
                                ->placeholder("Ex: 0")
                                ->maxLength(2)
                                ->required(fn ($get) => $get("payment_method") === "salary_account")
                                ->helperText("Dígito verificador da conta."),
                        ])
                        ->columnSpanFull(),

                    // BLOCO 2: CHAVE PIX (OBRIGATÓRIO SE PIX / OPCIONAL COMO RESERVA SE CONTA-SALÁRIO)
                    Grid::make(2)
                        ->visible(fn ($get) => $get("payment_method") !== "cash")
                        ->schema([
                            Select::make("pix_key_type")
                                ->label("Tipo de Chave PIX")
                                ->options([
                                    "cpf"    => "CPF",
                                    "email"  => "E-mail",
                                    "phone"  => "Telefone/WhatsApp",
                                    "random" => "Chave Aleatória",
                                ])
                                ->live()
                                ->required(fn ($get) => $get("payment_method") === "pix"),

                            TextInput::make("pix_key")
                                ->label("Chave PIX")
                                ->placeholder("Informe a chave PIX do colaborador")
                                ->required(fn ($get) => $get("payment_method") === "pix")
                                ->helperText(fn ($get) => $get("payment_method") === "salary_account" ? "Chave PIX reserva (opcional)." : null),
                        ])
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make("Benefícios & Escala")->schema([
                CheckboxList::make("work_days")
                    ->label("Dias de Trabalho (Escala Padrão)")
                    ->options([
                        "seg" => "Segunda",
                        "ter" => "Terça",
                        "qua" => "Quarta",
                        "qui" => "Quinta",
                        "sex" => "Sexta",
                        "sab" => "Sábado",
                        "dom" => "Domingo",
                    ])
                    ->columns(4),

                Toggle::make("receives_night_premium")
                    ->label("Recebe Adicional Noturno?")
                    ->default(false),

                Toggle::make("receives_hazard_pay")
                    ->label("Recebe Periculosidade?")
                    ->default(false),

                Select::make("kitchens")
                    ->relationship('kitchens', 'name')
                    ->label("Acesso a Praças de Produção (KDS)")
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText("Deixe vazio se ele não trabalha na cozinha/bar.")
                    ->visible(fn () => CompanySetting::getCached()?->kds_access_mode === 'individual')
                    ->columnSpanFull(),
            ]),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
