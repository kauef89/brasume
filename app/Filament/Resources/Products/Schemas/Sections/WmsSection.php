<?php

namespace App\Filament\Resources\Products\Schemas\Sections;

use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class WmsSection
{
    public static function make(): Section
    {
        return Section::make("WMS - Gestão de Saldo e Reposição por Local")
            ->description("Distribua fisicamente o estoque deste produto. O Saldo Atual configurado aqui reflete imediatamente no PDV.")
            ->icon("heroicon-o-arrows-right-left")
            // 🚀 AQUI: Oculta a sessão inteira se o produto exigir preparo na cozinha
            ->visible(fn(Get $get) =>
                $get("type") !== "combo" &&
                $get("track_inventory") === true &&
                $get("requires_preparation") !== true
            )
            ->schema([
                Forms\Components\Repeater::make("storageRules")
                    ->relationship("storageRules")
                    ->label("Locais de Armazenamento")
                    ->addActionLabel("Adicionar Local")
                    ->schema([
                        Forms\Components\Select::make("storage_location_id")
                            ->label("Local (Destino)")
                            ->relationship("location", "name")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(2),

                        Forms\Components\Select::make("source_location_id")
                            ->label("Puxar de onde? (Origem)")
                            ->relationship("sourceLocation", "name")
                            ->searchable()
                            ->preload()
                            ->helperText("De onde a equipe tira para abastecer o destino.")
                            ->columnSpan(2),

                        // 🚀 NOVO CAMPO DE SALDO
                        Forms\Components\TextInput::make("current_stock")
                            ->label("Saldo Atual")
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('any')
                            ->default(0)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make("max_capacity")
                            ->label("Capacidade")
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make("restock_threshold")
                            ->label("Ponto de Reposição")
                            ->numeric()
                            ->default(0)
                            ->helperText("Avisa a equipe quando o saldo chegar neste valor.")
                            ->columnSpan(2),
                    ])
                    ->columns(6)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string =>
                        isset($state['storage_location_id']) ? "Armazenamento e Saldo" : null
                    )
            ])
            ->columnSpanFull();
    }
}
