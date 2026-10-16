<?php

namespace App\Filament\Resources\Kitchens\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;

class KitchenForm
{
    /**
     * @return array<int,Section>
     */
    public static function getFormSchema(): array
    {
        return [
        SchemaComponents\Section::make("Detalhes da Praça / Cozinha")
                        ->schema([
                            FormComponents\TextInput::make("name")
                                ->label("Nome da Praça (Ex: Sushi Bar)")
                                ->required()
                                ->columnSpan(2),

                            // 🚀 O NOVO CAMPO DE TIPO DE PRAÇA
                            FormComponents\Select::make("type")
                                ->label("Tipo de Interface (KDS)")
                                ->options([
                                    'kitchen' => 'Cozinha Tradicional (Kanban c/ 3 Colunas)',
                                    'bar' => 'Preparo Rápido / Bar (Lista com Slide-to-Confirm)'
                                ])
                                ->default('kitchen')
                                ->required()
                                ->columnSpan(2),

                            FormComponents\Select::make("printer_id")
                                ->relationship("printer", "name")
                                ->label("Impressora Vinculada")
                                ->searchable()
                                ->preload()
                                ->columnSpan(2),

                            FormComponents\Toggle::make("is_active")
                                ->label("Ativa?")
                                ->default(true)
                                ->inline(false)
                                ->columnSpan(1),
                        ])
                        ->columns(7),
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
