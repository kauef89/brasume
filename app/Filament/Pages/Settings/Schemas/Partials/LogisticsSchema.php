<?php

namespace App\Filament\Pages\Settings\Schemas\Partials;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use App\Models\Category;

class LogisticsSchema
{
    public static function make(): array
    {
        return [
            Section::make('Logística e Entregas (Motoboys)')
                ->description('Regras para o aplicativo de entregas da equipe.')
                ->icon('heroicon-o-truck')
                ->schema([
                    Select::make('attention_categories')
                        ->label('Categorias de Atenção (Alerta Amarelo)')
                        ->helperText('Pedidos que contenham itens destas categorias aparecerão com destaque no PWA do Motoboy para evitar derramamentos.')
                        ->multiple()
                        ->options(Category::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
        ]),
        ];
    }
}
