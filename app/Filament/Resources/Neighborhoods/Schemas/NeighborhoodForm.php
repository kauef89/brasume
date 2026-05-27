<?php

namespace App\Filament\Resources\Neighborhoods\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;

class NeighborhoodForm
{
    /**
     * @return array<int,SchemaComponents\Section>
     */
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Section::make("Dados do Bairro")
                ->schema([
                    FormComponents\TextInput::make("name")
                        ->label("Nome do Bairro")
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(1),
                    FormComponents\TextInput::make("delivery_fee")
                        ->label('Taxa de Entrega (R$)')
                        ->required()
                        ->numeric()
                        ->default(0.0)
                        ->prefix('R$')
                        ->columnSpan(1),
                    FormComponents\TextInput::make("free_shipping_over")
                        ->label('Frete Grátis Acima De (R$)')
                        ->numeric()
                        ->prefix('R$')
                        ->helperText("Deixe em branco se não houver")
                        ->columnSpan(1),
                ])
                ->columns(3),
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
