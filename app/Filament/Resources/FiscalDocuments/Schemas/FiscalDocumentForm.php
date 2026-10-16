<?php

namespace App\Filament\Resources\FiscalDocuments\Schemas;

use Filament\Forms;

class FiscalDocumentForm
{
    public static function schema(): array
    {
        return [
            Forms\Components\Section::make('Detalhes do Documento')
                ->schema([
                    Forms\Components\TextInput::make('number')
                        ->label('Número')
                        ->disabled(),
                    Forms\Components\TextInput::make('series')
                        ->label('Série')
                        ->disabled(),
                    Forms\Components\TextInput::make('model')
                        ->label('Modelo')
                        ->disabled(),
                    Forms\Components\TextInput::make('status')
                        ->label('Status SEFAZ')
                        ->disabled(),
                    Forms\Components\TextInput::make('access_key')
                        ->label('Chave de Acesso')
                        ->columnSpanFull()
                        ->disabled(),
                    Forms\Components\TextInput::make('protocol')
                        ->label('Protocolo')
                        ->columnSpanFull()
                        ->disabled(),
                ])->columns(4),

            Forms\Components\Section::make('Conteúdo XML')
                ->schema([
                    Forms\Components\Textarea::make('xml_content')
                        ->label('XML Original')
                        ->rows(15)
                        ->columnSpanFull()
                        ->extraAttributes(['class' => 'font-mono text-xs'])
                        ->disabled(),
                ]),
        ];
    }
}
