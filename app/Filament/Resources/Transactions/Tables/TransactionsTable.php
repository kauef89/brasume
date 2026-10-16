<?php

namespace App\Filament\Resources\Transactions\Tables;


use App\Enums\TransactionType;use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions; // <- ESTA É A LINHA MÁGICA QUE FALTAVA!

class TransactionsTable 
{
    public static function configure(Table $table): Table 
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in' => 'Entrada',
                        'out' => 'Saída',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('bankAccount.name')
                    ->label('Conta / Cofre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable()
                    ->weight('bold'),

                // Colunas ocultas por padrão
                Tables\Columns\TextColumn::make('operator_name')
                    ->label('Operador')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('order.id')
                    ->label('Pedido #')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo de Movimento')
                    ->options(TransactionType::class),

                Tables\Filters\SelectFilter::make('bank_account_id')
                    ->relationship('bankAccount', 'name')
                    ->label('Filtrar por Conta'),
            ])
            ->actions([
                Actions\EditAction::make(), // Agora funciona!
            ]);
    }
}