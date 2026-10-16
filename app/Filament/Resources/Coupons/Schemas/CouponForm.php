<?php

namespace App\Filament\Resources\Coupons\Schemas;


use App\Enums\CouponType;use App\Models\Category;
use App\Models\Coupon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class CouponForm
{
    /**
     * @return array<int, Section>
     */
    public static function getFormSchema(): array
    {
        return [
            Section::make("Identificação & Regras Financeiras")
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make("name")
                                ->label("Nome da Campanha / Promoção")
                                ->placeholder("Ex: Combo Smash + Refri (Desconto Caixa)")
                                ->maxLength(150)
                                ->columnSpan(2),

                            TextInput::make("code")
                                ->label("Código do Cupom")
                                ->required()
                                ->unique(
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn(Unique $rule) => $rule->withoutTrashed(),
                                )
                                ->extraInputAttributes([
                                    "style" => "text-transform: uppercase",
                                ])
                                ->suffixAction(
                                    Action::make("gerar_aleatorio")
                                        ->icon("heroicon-m-sparkles")
                                        ->tooltip("Gerar código aleatório único")
                                        ->action(function (Set $set) {
                                            do {
                                                $codigoGerado = strtoupper(Str::random(8));
                                            } while (Coupon::where("code", $codigoGerado)->exists());

                                            $set("code", $codigoGerado);
                                        }),
                                )
                                ->columnSpan(1),
                        ]),

                    Textarea::make("description")
                        ->label("Descrição / Instruções Internas")
                        ->placeholder("Ex: Aplicar quando o cliente solicitar os itens avulsos do combo no caixa.")
                        ->rows(2)
                        ->columnSpanFull(),

                    Grid::make(3)
                        ->schema([
                            Select::make("type")
                                ->label("Tipo de Desconto")
                                ->options(CouponType::class)
                                ->required()
                                ->default("fixed"),

                            TextInput::make("value")
                                ->label("Valor do Desconto")
                                ->numeric()
                                ->required()
                                ->helperText("Se for Entrega Grátis, este valor é ignorado."),

                            Toggle::make("is_active")
                                ->label("Cupom Ativo?")
                                ->default(true)
                                ->inline(false),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make("Regras de Exclusividade e Limites")
                ->schema([
                    Select::make("customer_id")
                        ->label("Cupom Exclusivo (Opcional)")
                        ->relationship("customer", "name")
                        ->searchable()
                        ->preload()
                        ->helperText("Se preenchido, apenas este cliente poderá utilizar o cupom."),

                    TextInput::make("min_subtotal")
                        ->label("Valor Mínimo do Carrinho (R$)")
                        ->numeric()
                        ->default(0),

                    TextInput::make("global_usage_limit")
                        ->label("Limite de Uso Total")
                        ->numeric()
                        ->default(0)
                        ->helperText("0 = Ilimitado"),

                    TextInput::make("monthly_usage_limit")
                        ->label("Limite Mensal")
                        ->numeric()
                        ->default(0),

                    TextInput::make("user_usage_limit")
                        ->label("Limite por Cliente")
                        ->numeric()
                        ->default(1),

                    TextInput::make("usage_count")
                        ->label("Vezes Utilizado")
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (string $context): bool => $context === "edit"),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make("Validade e Restrições")
                ->schema([
                    DateTimePicker::make("starts_at")
                        ->label("Data de Início")
                        ->displayFormat("d/m/Y H:i"),

                    DateTimePicker::make("expires_at")
                        ->label("Data de Vencimento")
                        ->displayFormat("d/m/Y H:i"),

                    Select::make("restricted_categories")
                        ->label("Categorias Restritas")
                        ->multiple()
                        ->options(Category::pluck("name", "id"))
                        ->searchable()
                        ->helperText("Deixe em branco para permitir todas as categorias."),

                    Toggle::make("first_order_only")
                        ->label("Apenas Primeira Compra?")
                        ->helperText("Ideal para cupons de Growth.")
                        ->default(false)
                        ->inline(false),

                    Toggle::make("is_pos_only")
                        ->label("Cupom Interno (Exclusivo do PDV)")
                        ->helperText("Impede que clientes utilizem este cupom pelo Aplicativo/Delivery.")
                        ->default(false)
                        ->inline(false),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
