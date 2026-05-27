<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinancialRelationManager extends RelationManager
{
    protected static string $relationship = 'accountPayables'; // Usaremos a tabela de contas a pagar como base
    protected static ?string $title = 'Extrato Financeiro (NFes e Pagamentos)';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('due_date')->label('Vencimento')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('description')->label('Descrição')->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('Valor')->money('BRL'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                        'cancelled' => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => match($state) { 
                        'pending' => 'Pendente', 
                        'paid' => 'Pago', 
                        'cancelled' => 'Cancelado' 
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Pendente', 'paid' => 'Pago']),
            ])
            ->defaultSort('due_date', 'desc');
    }
}