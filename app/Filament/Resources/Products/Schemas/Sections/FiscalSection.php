<?php

namespace App\Filament\Resources\Products\Schemas\Sections;

use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class FiscalSection
{
    public static function make(): Section
    {
        return Section::make("Fiscal")
            ->icon("heroicon-o-document-text")
            ->visible(fn(Get $get) => $get("type") !== "combo")
            ->schema([
                Grid::make(3)->schema([
                    Forms\Components\TextInput::make("fiscal_ncm")
                        ->label("NCM")
                        ->maxLength(10),
                    Forms\Components\TextInput::make("fiscal_cest")
                        ->label("CEST")
                        ->maxLength(10),
                    Forms\Components\TextInput::make("fiscal_cfop")
                        ->label("CFOP Padrão")
                        ->maxLength(5),
                    Forms\Components\TextInput::make("fiscal_origin")
                        ->label("Origem (0 a 8)")
                        ->maxLength(2),
                    Forms\Components\TextInput::make("fiscal_csosn")
                        ->label("CSOSN / CST")
                        ->maxLength(4),
                    Forms\Components\Toggle::make("is_monofasico")
                        ->label("Produto Monofásico?")
                        ->default(false),
                ]),
            ])
            ->columnSpanFull();
    }
}
