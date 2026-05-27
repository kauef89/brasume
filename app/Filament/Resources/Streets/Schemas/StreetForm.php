<?php

namespace App\Filament\Resources\Streets\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;

class StreetForm
{
    /**
     * @return array<int,Section>
     */
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Section::make("Dados da Rua")
                ->schema([
                    FormComponents\TextInput::make("name")
                        ->label("Nome da Rua (Ex: Avenida Brasil)")
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),

                    FormComponents\TextInput::make("zip_code")
                        ->label("CEP Geral (Opcional)")
                        ->maxLength(255)
                        ->columnSpan(1),
                ])
                ->columns(3)
                ->columnSpanFull(),

            SchemaComponents\Section::make("Faixas de Numeração (Bairros)")
                ->description("Defina por quais bairros esta rua passa.")
                ->schema([
                    FormComponents\Repeater::make("segments")
                        ->relationship()
                        ->label("")
                        ->schema([
                            FormComponents\Select::make("neighborhood_id")
                                ->relationship("neighborhood", "name")
                                ->label("Bairro")
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpan(2),

                            FormComponents\TextInput::make("start_number")
                                ->label("Número Inicial")
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->columnSpan(1),

                            FormComponents\TextInput::make("end_number")
                                ->label("Número Final")
                                ->numeric()
                                ->helperText(
                                    "Deixe em branco se for até o fim da rua",
                                )
                                ->columnSpan(1),

                            FormComponents\TextInput::make("zip_code")
                                ->label("CEP Específico (Opcional)")
                                ->columnSpan(2),
                        ])
                        ->columns(6)
                        ->addActionLabel("Adicionar Faixa/Segmento")
                        ->reorderable()
                        ->collapsible(),
                ])
                ->columnSpanFull(),
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
