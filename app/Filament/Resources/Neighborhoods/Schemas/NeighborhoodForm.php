<?php

namespace App\Filament\Resources\Neighborhoods\Schemas;

use Filament\Forms\Components as FormComponents;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Schemas\Schema;

class NeighborhoodForm
{
    /**
     * @return array<int,SchemaComponents\Section>
     */
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Section::make("Dados do Bairro & Regras de Entrega")
                ->schema([
                    FormComponents\TextInput::make("name")
                        ->label("Nome do Bairro")
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),

                    FormComponents\TextInput::make("delivery_fee")
                        ->label('Taxa Cobrada do Cliente (R$)')
                        ->required()
                        ->numeric()
                        ->default(0.0)
                        ->prefix('R$')
                        ->helperText('Valor do frete somado ao pedido do cliente no checkout.')
                        ->columnSpan(1),

                    FormComponents\TextInput::make("courier_fee")
                        ->label('Repasse ao Motoboy (R$)')
                        ->numeric()
                        ->prefix('R$')
                        ->placeholder('Igual à taxa base')
                        ->helperText('Valor repassado ao motoboy neste bairro. Deixe vazio para usar a taxa padrão do entregador.')
                        ->columnSpan(1),

                    FormComponents\TextInput::make("free_shipping_over")
                        ->label('Frete Grátis Acima De (R$)')
                        ->numeric()
                        ->prefix('R$')
                        ->helperText("Deixe em branco se este bairro não tiver política de frete grátis.")
                        ->columnSpan(2),
                ])
                ->columns(2),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
