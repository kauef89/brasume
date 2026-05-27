<?php

namespace App\Filament\Resources\LegacyNeighborhoods\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;

class LegacyNeighborhoodForm
{
    public static function getFormSchema(): array
    {
        return [
            Section::make('Mapeamento de Bairro V1')->schema([
                TextInput::make('legacy_name')
                    ->label('Bairro no CSV (V1)')
                    ->required()
                    ->maxLength(255),

                // 🚀 Trocado de table_area_id para neighborhood_id
                Select::make('neighborhood_id')
                    ->label('Bairro Real (V3)')
                    ->relationship('neighborhood', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Selecione o bairro real para associar corretamente as taxas de entrega.'),
            ])->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
