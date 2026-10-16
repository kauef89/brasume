<?php
namespace App\Filament\Resources\Offers\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;

class OfferForm
{
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Section::make("Detalhes da Oferta")
                ->schema([
                    FormComponents\TextInput::make("title")
                        ->label(
                            "Título da Oferta (Ex: Leve um refri na faixa 💖)",
                        )
                        ->required()
                        ->columnSpan(2),

                    FormComponents\TextInput::make("promo_price")
                        ->label('Preço Promocional (R$)')
                        ->numeric()
                        ->prefix('R$')
                        ->helperText("Deixe em branco se for um brinde/grátis")
                        ->columnSpan(1),

                    FormComponents\Select::make("trigger_product_id")
                        ->relationship("triggerProduct", "name")
                        ->label("Produto Gatilho (Opcional)")
                        ->searchable()
                        ->preload()
                        ->helperText("O cliente precisa comprar qual produto para ganhar esta oferta?",
                        )
                        ->columnSpan(2),

                    FormComponents\Toggle::make("is_active")
                        ->label("Oferta Ativa?")
                        ->default(true)
                        ->inline(false)
                        ->columnSpan(1),
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
