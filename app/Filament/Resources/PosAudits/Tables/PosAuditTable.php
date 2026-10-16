<?php

namespace App\Filament\Resources\PosAudits\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\ViewAction;

class PosAuditTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data e Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('action')
                    ->label('Operação')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cancel_item' => 'Estorno de Item',
                        'edit_item' => 'Edição de Item',
                        'cancel_order' => 'Cancelamento de Pedido',
                        'cash_out' => 'Sangria de Caixa',
                        'apply_discount' => 'Desconto Manual',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cancel_item', 'cancel_order' => 'danger',
                        'edit_item', 'apply_discount' => 'warning',
                        default => 'primary',
                    }),

                TextColumn::make('reason')
                    ->label('Motivo / Justificativa')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('operator.name')
                    ->label('Operador')
                    ->searchable(),

                TextColumn::make('authorizer.name')
                    ->label('Autorizado por')
                    ->badge()
                    ->color(fn ($record) => $record->operator_id === $record->authorizer_id ? 'gray' : 'success')
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('A partir de'),
                        DatePicker::make('created_until')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    }),
                SelectFilter::make('authorizer_id')
                    ->label('Autorizador')
                    ->relationship('authorizer', 'name'),
            ])
            ->actions([
                ViewAction::make()->label('Detalhes')->iconButton(),
            ]);
    }
}
