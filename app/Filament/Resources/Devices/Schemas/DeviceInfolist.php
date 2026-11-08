<?php

namespace App\Filament\Resources\Devices\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;

class DeviceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação do Equipamento')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label('Nome')->weight('bold'),
                        TextEntry::make('type')->label('Função / Tipo')->badge(),
                        IconEntry::make('is_active')->label('Ativo')->boolean(),
                    ]),

                Section::make('Parâmetros de Rede e Hardware')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('terminal_code')->label('Código do Terminal (POS/TEF)')->placeholder('Não aplicável'),
                        TextEntry::make('assignment_scope')->label('Destinação Operacional')->badge(),
                        TextEntry::make('acquirer_name')->label('Adquirente')->placeholder('-'),
                        TextEntry::make('device_uuid')->label('UUID de Emparelhamento')->fontFamily('mono')->columnSpan(2),
                        TextEntry::make('last_active_at')->label('Último Acesso')->dateTime('d/m/Y H:i:s')->placeholder('Nunca'),
                    ]),
            ]);
    }
}