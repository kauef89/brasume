<?php

namespace App\Filament\Resources\AccountReceivables\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Actions\Action;

class AccountReceivablesTable 
{
    public static function configure(Table $table): Table 
    {
        return $table
            ->defaultGroup('due_date')
            ->groups([
                Group::make('due_date')->label('Data de Previsão')->date(),
                Group::make('bankAccount.name')->label('Conta Bancária'),
                Group::make('status')->label('Status'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('due_date')->label('Previsão')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('description')->label('Descrição')->searchable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Método')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bankAccount.name')->label('Destino')->badge()->color('info')->searchable(),
                Tables\Columns\TextColumn::make('gross_amount')->label('Venda')->money('BRL')->color('gray'),
                Tables\Columns\TextColumn::make('net_amount')->label('Líquido')->money('BRL')->weight('bold')->sortable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'A Receber',
                        'paid' => 'Caiu na Conta',
                        'anticipated' => 'Antecipado',
                        'cancelled' => 'Cancelado',
                        default => $state,
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => fn ($state) => in_array($state, ['paid', 'anticipated']),
                        'danger' => 'cancelled',
                    ]),
            ])
            ->defaultSort('due_date', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'A Receber',
                    'paid' => 'Caiu na Conta',
                    'anticipated' => 'Antecipado',
                ]),
            ])
            ->actions([
                // O Botão Mágico para dar baixa rápida do que pingou na conta!
                \Filament\Actions\Action::make('receber')
                    ->label('Dar Baixa')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('payment_date')
                            ->label('Data do Crédito no Banco')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => 'paid',
                            'payment_date' => $data['payment_date'],
                        ]);
                    }),
                    
                \Filament\Actions\EditAction::make()->iconButton(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}