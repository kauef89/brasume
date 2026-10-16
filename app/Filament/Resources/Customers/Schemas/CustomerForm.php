<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\Rules\Unique;

class CustomerForm
{
    /**
     * @return array<int,SchemaComponents\Tabs>
     */
    public static function getFormSchema(): array
    {
        return [
            Tabs::make("Abas do Cliente")
                ->tabs([
                    // ==========================================
                    // ABA 1: PESSOAL E CONTATOS
                    // ==========================================
                    Tabs\Tab::make("Dados e Contatos")
                        ->icon("heroicon-o-user")
                        ->schema([
                            SchemaComponents\Section::make("Dados Pessoais")
                                ->schema([
                                    FormComponents\TextInput::make("name")
                                        ->label("Nome Completo")
                                        ->required()
                                        ->columnSpan(2),
                                    FormComponents\TextInput::make("cpf")
                                        ->label("CPF")
                                        ->mask("999.999.999-99")
                                        ->stripCharacters([".", "-"])
                                        ->unique(
                                            ignoreRecord: true,
                                            modifyRuleUsing: fn(
                                                Unique $rule,
                                            ) => $rule->withoutTrashed(),
                                        )
                                        ->columnSpan(1),
                                    FormComponents\DatePicker::make(
                                        "birth_date",
                                    )
                                        ->label("Data de Nascimento")
                                        ->displayFormat("d/m/Y")
                                        ->columnSpan(1),
                                ])
                                ->columns(4),

                            SchemaComponents\Section::make("Contatos")
                                ->schema([
                                    FormComponents\TextInput::make("email")
                                        ->label("E-mail")
                                        ->email()
                                        ->columnSpan(2),
                                    FormComponents\TextInput::make("phone")
                                        ->label("WhatsApp / Telefone")
                                        ->mask("(99) 99999-9999")
                                        ->stripCharacters(["(", ")", "-", " "])
                                        ->columnSpan(2),
                                ])
                                ->columns(4),
                        ]),

                    // ==========================================
                    // ABA 2: ENDEREÇOS (is_active injetado!)
                    // ==========================================
                    Tabs\Tab::make("Endereços")
                        ->icon("heroicon-o-map-pin")
                        ->schema([
                            FormComponents\Repeater::make("addresses")
                                ->relationship("addresses")
                                ->label("")
                                ->schema([
                                    FormComponents\Select::make("nickname")
                                        ->label("Apelido")
                                        ->options([
                                            "Casa" => "Casa",
                                            "Trabalho" => "Trabalho",
                                            "Amor" => "Amor",
                                            "Pai/Mãe" => "Pai/Mãe",
                                            "Outro" => "Outro",
                                        ])
                                        ->default("Casa")
                                        ->required()
                                        ->columnSpan(2),

                                    FormComponents\Select::make("street_id")
                                        ->relationship("street", "name")
                                        ->label("Rua")
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live()
                                        ->createOptionForm(
                                            \App\Filament\Resources\Streets\Schemas\StreetForm::getFormSchema(),
                                        )
                                        ->afterStateUpdated(function (
                                            Set $set,
                                            $state,
                                        ) {
                                            if (!$state) {
                                                return;
                                            }
                                            $lastAddress = \App\Models\Address::where(
                                                "street_id",
                                                $state,
                                            )
                                                ->whereNotNull(
                                                    "neighborhood_id",
                                                )
                                                ->first();
                                            if ($lastAddress) {
                                                $set(
                                                    "neighborhood_id",
                                                    $lastAddress->neighborhood_id,
                                                );
                                            }
                                        })
                                        ->columnSpan(4),

                                    FormComponents\Select::make(
                                        "neighborhood_id",
                                    )
                                        ->relationship("neighborhood", "name")
                                        ->label("Bairro")
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->createOptionForm(
                                            \App\Filament\Resources\Neighborhoods\Schemas\NeighborhoodForm::getFormSchema(),
                                        )
                                        ->columnSpan(3),

                                    FormComponents\Toggle::make("no_number")
                                        ->label("Sem número?")
                                        ->live()
                                        ->afterStateUpdated(function (
                                            Set $set,
                                            $state,
                                        ) {
                                            if ($state) {
                                                $set("number", "S/N");
                                            } else {
                                                $set("number", null);
                                            }
                                        })
                                        ->columnSpan(1),

                                    FormComponents\TextInput::make("number")
                                        ->label("Número")
                                        ->required(
                                            fn(Get $get) => !$get("no_number"),
                                        )
                                        ->readOnly(
                                            fn(Get $get) => $get("no_number"),
                                        )
                                        ->columnSpan(2),

                                    FormComponents\TextInput::make("complement")
                                        ->label("Complemento")
                                        ->required(
                                            fn(Get $get) => $get("no_number"),
                                        )
                                        ->columnSpan(3),

                                    FormComponents\TextInput::make("reference")
                                        ->label("Ponto de Referência")
                                        ->columnSpan(2), // 🚀 Reduzido de 3 para 2

                                    // 🚀 O NOVO TOGGLE CONTROLADOR AQUI:
                                    FormComponents\Toggle::make("is_active")
                                        ->label("Ativo?")
                                        ->default(true)
                                        ->columnSpan(1), // 🚀 Ocupa a 1 coluna restante para fechar as 6!

                                    FormComponents\TextInput::make(
                                        "location_link",
                                    )
                                        ->label(
                                            "Link de Localização (Maps/WhatsApp)",
                                        )
                                        ->url()
                                        ->required(
                                            fn(Get $get) => $get("no_number"),
                                        )
                                        ->columnSpan(6),
                                ])
                                ->columns(6)
                                ->addActionLabel("Adicionar Novo Endereço")
                                ->addAction(
                                    fn($action) => $action->color("primary"),
                                )
                                ->collapsible()
                                ->itemLabel(
                                    fn(array $state): ?string => $state[
                                        "nickname"
                                    ] ?? "Novo Endereço",
                                ),
                        ]),

                    // ==========================================
                    // ABA 3: PEDIDOS
                    // ==========================================
                    Tabs\Tab::make("Pedidos")
                        ->icon("heroicon-o-shopping-bag")
                        ->schema([
                            FormComponents\Placeholder::make("pedidos_aviso")
                                ->label("Histórico de Compras do Cliente")
                                ->content(
                                    "Para visualizar a tabela completa de pedidos deste cliente, salve as alterações e verifique a tabela inteligente no final desta página.",
                                ),
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
