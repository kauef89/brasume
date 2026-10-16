<?php

namespace App\Filament\Resources\Products\Schemas\Sections;

use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class ProductionSection
{
    public static function make(): Section
    {
        return Section::make("Produção")
            ->icon("heroicon-o-fire")
            ->visible(fn(Get $get) => $get("type") !== "combo")
            ->schema([
                Grid::make(2)->schema([
                    Forms\Components\Select::make("kitchen_id")
                        ->label("Setor de Preparo (Cozinha)")
                        ->relationship("kitchen", "name")
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make("print_station_id")
                        ->label("Impressora de Produção")
                        ->relationship("printer", "name")
                        ->searchable()
                        ->preload(),
                ]),
                Grid::make(3)->schema([
                    Forms\Components\TextInput::make("prep_time_minutes")
                        ->label("Tempo de Preparo (Minutos)")
                        ->numeric(),
                    Forms\Components\Toggle::make("requires_preparation")
                        ->label("Exige Preparo?")
                        ->default(true),
                ]),
            ])
            ->columnSpanFull();
    }
}
