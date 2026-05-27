<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\CheckboxList;

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
                        ->options([
                            "clt_44" => "CLT (44h)",
                            "clt_40" => "CLT (40h)",
                            "freelance_fixed" => "Freelancer Fixo",
                            "freelance_eventual" => "Freelancer Eventual",
                        ])
                        ->required()
                        ->live()
                        ->columnSpan(1),

                    TextInput::make("base_remuneration")
                        ->label(
                            fn($get) => str_contains(
                                $get("employment_type") ?? "",
                                "clt",
                            )
                                ? "Salário Base"
                                : "Valor da Diária",
                        )
                        ->numeric()
                        ->prefix('R$')
                        ->required()
                        ->columnSpan(1),

                    Select::make("payment_cycle")
                        ->label("Ciclo de Pagamento")
                        ->options([
                            "daily" => "Diário",
                            "weekly" => "Semanal",
                            "biweekly" => "Quinzenal",
                            "monthly" => "Mensal",
                        ]),

                    TextInput::make("transportation_voucher")
                        ->label('Vale Transporte (R$)')
                        ->numeric()
                        ->prefix('R$'),
                ])
                ->columns(2),

            Section::make("Benefícios & Escala")->schema([
                CheckboxList::make("work_days")
                    ->label("Dias de Trabalho")
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

                Toggle::make("receives_night_premium")->label(
                    "Recebe Adicional Noturno?",
                ),

                Toggle::make("receives_hazard_pay")->label(
                    "Recebe Periculosidade?",
                ),
            ]),
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
