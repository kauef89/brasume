<?php

namespace App\Filament\Resources\PrintJobs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class PrintJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),

                TextColumn::make('order_id')
                    ->label('Pedido Associado')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '-')
                    ->searchable(),

                TextColumn::make('printer.name')
                    ->label('Impressora de Destino')
                    ->searchable(),

                TextColumn::make('job_type')
                    ->label('Tipo de Trabalho')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'kitchen_ticket' => 'Comanda Cozinha',
                        'customer_receipt' => 'Recibo Cliente',
                        'conference_bill' => 'Conferência Mesa',
                        'delivery_dispatch' => 'Via Motoboy',
                        'freelancer_receipt' => 'Vale Freelancer',
                        'test_page' => 'Página de Teste',
                        default => $state,
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Data do Disparo')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendente',
                        'success' => 'Impresso com Sucesso',
                        'failed'  => 'Falhou',
                    ]),
                \Filament\Tables\Filters\SelectFilter::make('printer_id')
                    ->label('Impressora')
                    ->relationship('printer', 'name'),
                \Filament\Tables\Filters\SelectFilter::make('job_type')
                    ->label('Tipo de Trabalho')
                    ->options([
                        'kitchen_ticket'    => 'Comanda Cozinha',
                        'customer_receipt'  => 'Recibo Cliente',
                        'conference_bill'   => 'Conferência Mesa',
                        'delivery_dispatch' => 'Via Motoboy',
                        'freelancer_receipt'=> 'Vale Freelancer',
                        'test_page'         => 'Página de Teste',
                    ]),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('reenviar')
                    ->label('Reenviar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'failed')
                    ->requiresConfirmation()
                    ->modalHeading('Reenviar Trabalho de Impressão')
                    ->modalDescription('O trabalho voltará ao estado pendente para que o agente local (Bridge) tente imprimir novamente.')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'pending',
                            'attempts' => 0,
                            'error_message' => null,
                        ]);
                        \Filament\Notifications\Notification::make()->title('Trabalho recolocado na fila!')->success()->send();
                    }),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(), // Permite limpar logs velhos em lote
                ]),
            ]);
    }
}
