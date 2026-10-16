<?php

namespace App\Filament\Resources\PosAudits\Schemas;

use Filament\Schemas\Schema; // 🚀 Filament v4
use Filament\Schemas\Components\Section; // 🚀 Layouts agora vêm do Schema
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;

class PosAuditInfolist
{
    // 🚀 Assinatura atualizada para receber o Schema
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([ // 🚀 No v4 usamos components() na raiz do Schema
                Section::make('Detalhes da Auditoria')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('action')->label('Tipo de Operação'),
                        TextEntry::make('reason')->label('Justificativa'),
                        TextEntry::make('operator.name')->label('Operador Logado'),
                        TextEntry::make('authorizer.name')->label('Autorizado por (PIN)'),
                        TextEntry::make('created_at')->label('Data e Hora Exata')->dateTime('d/m/Y H:i:s'),
                        KeyValueEntry::make('context')->label('Contexto da Operação')->columnSpanFull(),
                    ])
            ]);
    }
}
