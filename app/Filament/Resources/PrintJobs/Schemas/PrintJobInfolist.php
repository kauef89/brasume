<?php

namespace App\Filament\Resources\PrintJobs\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;

class PrintJobInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalhes do Trabalho')
                    ->columnSpanFull() // 🚀 FORÇA A SEÇÃO A OCUPAR A LINHA INTEIRA
                    ->columns(3)
                    ->schema([
                        // 🚀 MOSTRA O NOME NO SO (COM O APELIDO ENTRE PARÊNTESES)
                        TextEntry::make('printer.os_name')
                            ->label('Impressora')
                            ->formatStateUsing(fn ($record) => $record->printer ? "{$record->printer->os_name} ({$record->printer->name})" : 'Impressora Removida'),

                        TextEntry::make('job_type')
                            ->label('Tipo')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'kitchen_ticket' => 'Comanda Cozinha',
                                'customer_receipt' => 'Recibo Cliente',
                                'conference_bill' => 'Conferência Mesa',
                                'delivery_dispatch' => 'Via Motoboy',
                                'freelancer_receipt' => 'Vale Freelancer',
                                'test_page' => 'Página de Teste', // 🚀 TRADUÇÃO ADICIONADA AQUI!
                                default => $state,
                            }),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'success' => 'success',
                                'failed' => 'danger',
                                'pending' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('order_id')
                            ->label('Pedido Associado')
                            ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '-'),

                        TextEntry::make('attempts')
                            ->label('Tentativas de Envio'),

                        TextEntry::make('created_at')
                            ->label('Data do Disparo')
                            ->dateTime('d/m/Y H:i:s'),
                    ]),

                Section::make('Mensagem de Erro (Retorno da Bridge)')
                    ->columnSpanFull() // 🚀 FORÇA A FICAR NA LINHA DE BAIXO
                    ->schema([
                        TextEntry::make('error_message')
                            ->hiddenLabel()
                            ->color('danger'),
                    ])
                    ->visible(fn ($record) => !empty($record->error_message)), // Só aparece se houver erro!

                Section::make('Carga de Dados (Blueprint do Recibo)')
                    ->columnSpanFull() // 🚀 FORÇA A FICAR NA LINHA DE BAIXO
                    ->schema([
                        TextEntry::make('payload')
                            ->hiddenLabel()
                            ->formatStateUsing(fn ($state) => '<pre style="font-size:12px; overflow-x:auto; background:#f4f4f5; padding:10px; border-radius:8px;">' . json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>')
                            ->html(),
                    ]),
            ]);
    }
}
