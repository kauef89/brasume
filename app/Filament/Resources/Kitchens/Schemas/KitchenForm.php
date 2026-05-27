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
            SchemaComponents\Section::make("Detalhes da Cozinha")
                ->schema([
                    FormComponents\TextInput::make("name")
                        ->label("Nome da Cozinha (Ex: Sushi Bar)")
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
                ->columns(5),
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
