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
                // Pode adicionar um SelectFilter pelo 'status' aqui no futuro
            ])
            ->recordActions([
                ViewAction::make(), // 🚀 Apenas Visualizar!
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(), // Permite limpar logs velhos em lote
                ]),
            ]);
    }
}
