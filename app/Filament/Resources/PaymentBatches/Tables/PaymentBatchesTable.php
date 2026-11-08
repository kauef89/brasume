<?php

namespace App\Filament\Resources\PaymentBatches\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class PaymentBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Lote #')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Identificação / Descrição')
                    ->searchable()
                    ->weight('semibold')
                    ->wrap(),

                TextColumn::make('total_amount')
                    ->label('Valor Total')
                    ->money('BRL')
                    ->sortable()
                    ->weight('black'),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'processed', 'paid', 'approved' => 'success',
                        'pending', 'waiting', 'generated' => 'warning',
                        'cancelled', 'rejected', 'error' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'processed' => 'Processado',
                        'paid'      => 'Liquidado',
                        'approved'  => 'Aprovado',
                        'pending'   => 'Pendente',
                        'generated' => 'Arquivo Gerado',
                        'cancelled' => 'Cancelado',
                        default     => ucfirst($state ?? 'N/D'),
                    }),

                TextColumn::make('payment_date')
                    ->label('Data de Pagamento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Gerado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação do Lote')
                    ->options([
                        'pending'   => 'Pendente',
                        'generated' => 'Arquivo Gerado',
                        'processed' => 'Processado',
                        'paid'      => 'Liquidado',
                        'cancelled' => 'Cancelado',
                    ]),

                Filter::make('payment_date')
                    ->form([
                        DatePicker::make('from')->label('Pagamento De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('payment_date', '>=', $date))
                        ->when($data['until'], fn ($q, $date) => $q->whereDate('payment_date', '<=', $date))
                    ),
            ]);
    }
}