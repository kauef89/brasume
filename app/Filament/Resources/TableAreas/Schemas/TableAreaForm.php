<?php

namespace App\Filament\Resources\TableAreas\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;

class TableAreaForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make("Localização")
                ->description("Defina o nome do ambiente do restaurante.")
                ->schema([
                    TextInput::make("name")
                        ->label("Nome da Área (Ex: Salão Principal, Varanda)")
                        ->required()
                        ->maxLength(255),
                ]),

            Section::make("Mesas Deste Ambiente")
                ->description("Adicione as mesas que pertencem a esta área.")
                ->schema([
                    Repeater::make("diningTables")
                        ->relationship()
                        ->label("")
                        ->addActionLabel("Adicionar Mesa")
                        ->reorderable(false)
                        ->columns(3)
                        ->schema([
                            TextInput::make("name")
                                ->label("Identificação (Ex: 01, Mesa 02)")
                                ->required()
                                ->columnSpan(2),

                            Select::make("status")
                                ->label("Status Atual")
                                ->options([
                                    "available" => "Livre",
                                    "occupied" => "Ocupada",
                                    "closing" => "Fechando (Conta)",
                                ])
                                ->default("available")
                                ->required()
                                ->columnSpan(1),
                        ]),
                ]),
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
