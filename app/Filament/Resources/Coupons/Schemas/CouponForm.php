<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Coupon;
use Illuminate\Validation\Rules\Unique;

class CouponForm
{
    /**
     * @return array<int,SchemaComponents\Section>
     */
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Section::make("Configuração Principal")
                ->schema([
                    FormComponents\TextInput::make("code")
                        ->label("Código do Cupom (Ex: NATAL10)")
                        ->required()
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn(
                                Unique $rule,
                            ) => $rule->withoutTrashed(),
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
                                        $codigoGerado = strtoupper(
                                            Str::random(8),
                                        );
                                    } while (
                                        Coupon::where(
                                            "code",
                                            $codigoGerado,
                                        )->exists()
                                    );

                                    $set("code", $codigoGerado);
                                }),
                        ),

                    FormComponents\Select::make("type")
                        ->label("Tipo de Desconto")
                        ->options([
                            "fixed" => 'Fixo (R$)',
                            "percent" => "Porcentagem (%)",
                        ])
                        ->required()
                        ->default("fixed"),
                    FormComponents\TextInput::make("value")
                        ->label("Valor do Desconto")
                        ->numeric()
                        ->required(),
                    FormComponents\Toggle::make("is_active")
                        ->label("Cupom Ativo?")
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(4)
                ->columnSpanFull(),

            SchemaComponents\Section::make("Regras e Limites")
                ->schema([
                    FormComponents\TextInput::make("min_subtotal")
                        ->label('Valor Mínimo do Carrinho (R$)')
                        ->numeric()
                        ->default(0),
                    FormComponents\TextInput::make("global_usage_limit")
                        ->label("Limite de Uso Total")
                        ->numeric()
                        ->default(0)
                        ->helperText("0 = Ilimitado"),
                    FormComponents\TextInput::make("monthly_usage_limit")
                        ->label("Limite de Uso Mensal")
                        ->numeric()
                        ->default(0),
                    FormComponents\TextInput::make("user_usage_limit")
                        ->label("Limite por Cliente")
                        ->numeric()
                        ->default(1),
                ])
                ->columns(4)
                ->columnSpanFull(),

            SchemaComponents\Section::make("Validade e Restrições")
                ->schema([
                    FormComponents\DateTimePicker::make("starts_at")
                        ->label("Data de Início")
                        ->displayFormat("d/m/Y H:i"),
                    FormComponents\DateTimePicker::make("expires_at")
                        ->label("Data de Vencimento")
                        ->displayFormat("d/m/Y H:i"),
                    FormComponents\Select::make("restricted_categories")
                        ->label("Categorias Restritas")
                        ->multiple()
                        ->options(Category::all()->pluck("name", "id"))
                        ->searchable()
                        ->helperText(
                            "Deixe em branco para permitir todas as categorias.",
                        ),
                    FormComponents\Toggle::make("first_order_only")
                        ->label("Apenas Primeira Compra?")
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
