<?php
namespace App\Filament\Resources\Orders\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;

class OrderForm
{
    /**
     * @return void
     */
    private static function updateItemTotal(
        Get $get,
        Set $set,
        bool $isModifier = false,
    ) {
        $baseGet = $isModifier ? fn($k) => $get("../../" . $k) : $get;
        $baseSet = $isModifier ? fn($k, $v) => $set("../../" . $k, $v) : $set;

        $unitPrice = (float) ($baseGet("unit_price") ?? 0);
        $qty = (float) ($baseGet("quantity") ?? 1);

        $modsTotal = 0;
        $modifiers = $baseGet("modifiers") ?? [];
        foreach ($modifiers as $mod) {
            $modsTotal +=
                ((float) ($mod["price"] ?? 0)) *
                ((float) ($mod["quantity"] ?? 1));
        }

        $baseSet("total_price", round(($unitPrice + $modsTotal) * $qty, 2));
    }
    /**
     * @return array<int,SchemaComponents\Section>
     */
    public static function getFormSchema(): array
    {
        return [
            // CABEÇALHO DO PEDIDO
            SchemaComponents\Section::make("Identificação e Roteamento")
                ->schema([
                    FormComponents\Select::make("type")
                        ->label("Modalidade")
                        ->options([
                            "delivery" => "Delivery",
                            "counter" => "Balcão",
                            "pickup" => "Retirada (App)",
                            "table" => "Mesa",
                        ])
                        ->required()
                        ->live()
                        ->columnSpan(1),
                    FormComponents\Select::make("status")
                        ->label("Status do Pedido")
                        ->options([
                            "pending" => "Pendente",
                            "preparing" => "Preparando",
                            "ready" => "Pronto",
                            "out_for_delivery" => "Em Entrega",
                            "closed" => "Concluído",
                            "cancelled" => "Cancelado",
                        ])
                        ->required()
                        ->default("pending")
                        ->columnSpan(1),

                    FormComponents\Select::make("customer_id")
                        ->relationship("customer", "name") // <-- Alterado para relationship
                        ->label("Cliente Cadastrado")
                        ->searchable()
                        ->preload()
                        ->live()
                        ->createOptionForm(
                            \App\Filament\Resources\Customers\Schemas\CustomerForm::getFormSchema(),
                        ) // <-- FASE 3: Criação de Cliente
                        ->columnSpan(1),

                    FormComponents\Select::make("address_id")
                        ->label("Endereço de Entrega")
                        ->relationship(
                            name: "address",
                            modifyQueryUsing: fn(
                                Builder $query,
                                Get $get,
                            ) => $query->where(
                                "customer_id",
                                $get("customer_id"),
                            ),
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn($record) => ($record->street
                                ? $record->street->name
                                : "Rua") .
                                ", Nº " .
                                $record->number,
                        )
                        ->visible(fn(Get $get) => $get("type") === "delivery")
                        ->searchable()
                        ->preload()
                        ->columnSpan(1),
                    FormComponents\TextInput::make("customer_name")
                        ->label("Nome Avulso")
                        ->visible(
                            fn(Get $get) => in_array($get("type"), [
                                "counter",
                                "table",
                            ]),
                        )
                        ->columnSpan(1),
                    FormComponents\TextInput::make("table_number")
                        ->label("Número da Mesa")
                        ->visible(fn(Get $get) => $get("type") === "table")
                        ->columnSpan(1),
                ])
                ->columns(3)
                ->columnSpanFull(),

            // CARRINHO DE COMPRAS
            SchemaComponents\Section::make("Carrinho de Compras")
                ->schema([
                    FormComponents\Repeater::make("items")
                        ->relationship("items")
                        ->schema([
                            SchemaComponents\Grid::make(11)->schema([
                                FormComponents\Select::make("product_id")
                                    ->relationship("product", "name")
                                    ->label("Vincular ao Cadastro")
                                    ->searchable()
                                    ->live()
                                    ->createOptionForm(
                                        \App\Filament\Resources\Products\Schemas\ProductForm::getFormSchema(),
                                    ) // <-- FASE 3: Criação de Produto Rápida
                                    ->afterStateUpdated(function (
                                        $state,
                                        Set $set,
                                        Get $get,
                                    ) {
                                        if (
                                            $state &&
                                            ($produto = \App\Models\Product::find(
                                                $state,
                                            ))
                                        ) {
                                            $set(
                                                "product_name",
                                                $produto->name,
                                            );
                                            $set(
                                                "unit_price",
                                                $produto->dine_in_price,
                                            );
                                            self::updateItemTotal($get, $set);
                                        }
                                    })
                                    ->columnSpan(3),

                                FormComponents\TextInput::make("product_name")
                                    ->label("Nome (Recibo)")
                                    ->required()
                                    ->columnSpan(3),

                                FormComponents\TextInput::make("quantity")
                                    ->label("Qtd")
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(
                                        fn(
                                            Get $get,
                                            Set $set,
                                        ) => self::updateItemTotal($get, $set),
                                    )
                                    ->columnSpan(1),

                                FormComponents\TextInput::make("unit_price")
                                    ->label("Preço Unit.")
                                    ->numeric()
                                    ->step(0.01)
                                    ->prefix('R$')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(
                                        fn(
                                            Get $get,
                                            Set $set,
                                        ) => self::updateItemTotal($get, $set),
                                    )
                                    ->columnSpan(2),

                                FormComponents\TextInput::make("total_price")
                                    ->label("Total do Item")
                                    ->numeric()
                                    ->step(0.01)
                                    ->prefix('R$')
                                    ->required()
                                    ->readOnly()
                                    ->columnSpan(2),
                            ]),

                            FormComponents\Repeater::make("modifiers")
                                ->relationship("modifiers")
                                ->label("Adicionais e Observações")
                                ->schema([
                                    FormComponents\TextInput::make("name")
                                        ->label(
                                            "Descrição (Ex: +Bacon, Sem Cebola)",
                                        )
                                        ->required()
                                        ->columnSpan(2),
                                    FormComponents\TextInput::make("quantity")
                                        ->label("Qtd")
                                        ->numeric()
                                        ->default(1)
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(
                                            fn(
                                                Get $get,
                                                Set $set,
                                            ) => self::updateItemTotal(
                                                $get,
                                                $set,
                                                true,
                                            ),
                                        )
                                        ->columnSpan(1),
                                    FormComponents\TextInput::make("price")
                                        ->label("Preço Adicional")
                                        ->numeric()
                                        ->step(0.01)
                                        ->default(0)
                                        ->live()
                                        ->afterStateUpdated(
                                            fn(
                                                Get $get,
                                                Set $set,
                                            ) => self::updateItemTotal(
                                                $get,
                                                $set,
                                                true,
                                            ),
                                        )
                                        ->columnSpan(1),
                                ])
                                ->columns(4)
                                ->addActionLabel("Adicionar Extra")
                                ->collapsible()
                                ->collapsed(),

                            FormComponents\TextInput::make("notes")
                                ->label("Observação Interna do Item")
                                ->columnSpanFull(),
                        ])
                        ->addActionLabel("Adicionar Produto ao Pedido")
                        ->collapsible()
                        ->defaultItems(0),
                ])
                ->columnSpanFull(),

            // PAGAMENTOS E TOTAIS
            SchemaComponents\Section::make("Financeiro e Pagamentos")
                ->schema([
                    SchemaComponents\Grid::make(1)
                        ->schema([
                            FormComponents\TextInput::make("subtotal")
                                ->label("Subtotal")
                                ->numeric()
                                ->step(0.01)
                                ->default(0)
                                ->prefix('R$'),
                            FormComponents\TextInput::make("delivery_fee")
                                ->label("Taxa de Entrega")
                                ->numeric()
                                ->step(0.01)
                                ->default(0)
                                ->prefix('R$'),

                            FormComponents\Select::make("coupon_id")
                                ->relationship("coupon", "code")
                                ->label("Cupom Aplicado")
                                ->createOptionForm(
                                    \App\Filament\Resources\Coupons\Schemas\CouponForm::getFormSchema(),
                                ), // <-- FASE 3: Criação de Cupão

                            FormComponents\TextInput::make("discount")
                                ->label("Desconto Adicional")
                                ->numeric()
                                ->step(0.01)
                                ->default(0)
                                ->prefix('R$'),
                            FormComponents\TextInput::make("total")
                                ->label("TOTAL DO PEDIDO")
                                ->numeric()
                                ->step(0.01)
                                ->required()
                                ->prefix('R$')
                                ->extraInputAttributes([
                                    "style" =>
                                        "font-weight: bold; font-size: 1.2rem;",
                                ]),
                            FormComponents\Select::make("payment_status")
                                ->label("Status Geral")
                                ->options([
                                    "pending" => "Pendente",
                                    "partial" => "Parcialmente Pago",
                                    "paid" => "Pago",
                                    "cancelled" => "Cancelado",
                                ])
                                ->required()
                                ->default("pending"),
                        ])
                        ->columnSpan(1),

                    SchemaComponents\Grid::make(1)
                        ->schema([
                            FormComponents\Repeater::make("payments")
                                ->relationship("payments")
                                ->label("Lançamentos de Pagamento")
                                ->schema([
                                    FormComponents\Select::make("method")
                                        ->label("Método")
                                        ->options([
                                            "pix" => "Pix",
                                            "credit_card" =>
                                                "Cartão de Crédito",
                                            "debit_card" => "Cartão de Débito",
                                            "cash" => "Dinheiro",
                                        ])
                                        ->required()
                                        ->columnSpan(2),
                                    FormComponents\TextInput::make("amount")
                                        ->label("Valor")
                                        ->numeric()
                                        ->step(0.01)
                                        ->prefix('R$')
                                        ->required()
                                        ->columnSpan(2),
                                    FormComponents\Toggle::make("is_paid")
                                        ->label("Pago?")
                                        ->default(true)
                                        ->columnSpan(1),
                                ])
                                ->columns(5)
                                ->addActionLabel("Registrar Pagamento"),
                        ])
                        ->columnSpan(2),
                ])
                ->columns(3)
                ->columnSpanFull(),
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
