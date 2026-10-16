<?php

namespace App\Filament\Resources\StorageLocations\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Toggle;

class StorageLocationForm
{
    public static function schema(): array
    {
        return [
            Section::make('Detalhes do Espaço Físico')
                ->description('Cadastre geladeiras, prateleiras e câmaras frias.')
                ->schema([
                    TextInput::make('name')
                        ->label('Nome do Local')
                        ->placeholder('Ex: Cervejeira 1, Câmara Fria')
                        ->required()
                        ->maxLength(255),

                    Select::make('type')
                        ->label('Tipo de Local')
                        ->options([
                            'display' => 'Exposição (Frente de Loja)',
                            'intermediate' => 'Intermediário (Geladeira Interna)',
                            'storage' => 'Retaguarda (Estoque Seco/Câmara)',
                        ])
                        ->required()
                        ->default('storage')
                        ->native(false),

                    Textarea::make('description')
                        ->label('Descrição / Orientação')
                        ->placeholder('Onde fica este local?')
                        ->columnSpanFull(),

                    Grid::make(2)->schema([
                        TextInput::make('sort_order')
                            ->label('Ordem da Rota (Trajeto Físico)')
                            ->numeric()
                            ->default(0)
                            ->helperText('Organize a ordem para guiar o colaborador fisicamente.'),

                        Toggle::make('is_active')
                            ->label('Ativo')
                            ->default(true)
                            ->inline(false),
                    ]),
                ])
        ];
    }
}
