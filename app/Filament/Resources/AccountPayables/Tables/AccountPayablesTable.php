<?php

namespace App\Filament\Resources\AccountPayables\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;

class AccountPayablesTable
{
    public static function build(Table $table): Table
    {
        return $table
            ->defaultGroup('due_date')
            ->groups([
                Group::make('due_date')
                    ->label('Vencimento')
                    ->date(),
                Group::make('status')
                    ->label('Status'),
                Group::make('supplier.name')
                    ->label('Fornecedor'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record->status === 'pending' && $record->due_date < now() ? 'danger' : null), 
                    
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(40),
                    
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'paid' => 'Pago',
                        'cancelled' => 'Cancelado',
                        default => $state,
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'cancelled',
                    ]),
            ])
            ->defaultSort('due_date', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pendente',
                        'paid' => 'Pago',
                        'cancelled' => 'Cancelado',
                    ]),
                TernaryFilter::make('is_late')
                    ->label('Atrasadas')
                    ->queries(
                        true: fn ($query) => $query->where('status', 'pending')->whereDate('due_date', '<', now()),
                        false: fn ($query) => $query->whereDate('due_date', '>=', now()),
                    )
            ])
            ->actions([
                Action::make('pay')
                    ->label('Baixar')
                    ->icon('heroicon-m-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        DatePicker::make('payment_date')
                            ->label('Data em que foi pago')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'paid',
                            'payment_date' => $data['payment_date'],
                        ]);
                    }),
                    
                EditAction::make(),
            ]);
    }
}