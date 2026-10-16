<?php

namespace App\Filament\Resources\Coupons\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';
    protected static ?string $title = 'Histórico de Utilização (Métricas)';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Pedido #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data de Uso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->placeholder('Visitante / Não Identificado'),

                // Métrica 1: O custo da campanha
                Tables\Columns\TextColumn::make('discount')
                    ->label('Desconto Concedido (Custo)')
                    ->money('BRL')
                    ->color('danger')
                    ->summarize(
                        Summarizer::make()
                            ->label('Custo Total')
                            ->money('BRL')
                    ),

                // Métrica 2: A receita gerada
                Tables\Columns\TextColumn::make('total')
                    ->label('Valor Pago (Receita)')
                    ->money('BRL')
                    ->color('success')
                    ->weight('bold')
                    ->summarize(
                        Summarizer::make()
                            ->label('Receita Total')
                            ->money('BRL')
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status do Pedido')
                    ->badge()
                    ->colors([
                        'success' => 'closed',
                        'warning' => 'pending',
                        'danger' => 'cancelled',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status do Pedido')
                    ->options([
                        'closed' => 'Finalizados',
                        'cancelled' => 'Cancelados',
                    ]),
            ])
            ->headerActions([])
            ->actions([
                Action::make('view_order')
                    ->label('Ver Pedido')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record): string => \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $record]))
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
