<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Enums\EmploymentType;
use App\Enums\PaymentCycle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\CheckboxList;
use App\Models\CompanySetting;

class EmployeeForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make("Informações Básicas")
                ->schema([
                    TextInput::make("name")->label("Nome Completo")->required(),
                    TextInput::make("cpf")
                        ->label("CPF")
                        ->mask("999.999.999-99")
                        ->maxLength(14),
                    TextInput::make("email")->email()->label("E-mail"),
                    TextInput::make("phone")->label("Telefone/WhatsApp"),

                    // 🚀 TRAVA DE COLABORADOR
                    Toggle::make("is_active")
                        ->label("Colaborador Ativo")
                        ->default(true)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make("Pagamento & PIX")
                ->schema([
                    Select::make("pix_key_type")
                        ->label("Tipo de Chave PIX")
                        ->options([
                            "cpf" => "CPF",
                            "email" => "E-mail",
                            "phone" => "Telemóvel",
                            "random" => "Chave Aleatória",
                        ])
                        ->live(),
                    TextInput::make("pix_key")->label("Chave PIX"),
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

                    // 🚀 RÓTULO MÁGICO: Vinculado aos casos do Enum tipado
                    TextInput::make("base_remuneration")
                        ->label(function ($get) {
                            $type = $get("employment_type");
                            $val = $type instanceof EmploymentType ? $type->value : $type;
                            return match ($val) {
                                'clt_44', 'clt_40', 'intern' => 'Salário Base',
                                'partner' => 'Pró-Labore',
                                'pj' => 'Valor do Contrato',
                                default => 'Valor da Diária',
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
                    ->visible(fn() => \App\Models\CompanySetting::getCached()?->kds_access_mode === 'individual')
                    ->columnSpanFull(),
            ]),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
