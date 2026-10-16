<?php

namespace App\Filament\Resources\RecurringExpenses\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

// 🚀 IMPORTS EXPLÍCITOS DAS AÇÕES (Obrigatório no Filament v3/v4)
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class AccountPayablesRelationManager extends RelationManager
{
    protected static string $relationship = 'accountPayables';
    protected static ?string $title = 'Histórico de Faturas / Pagamentos';
    protected static string | \UnitEnum | null $navigationGroup = 'heroicon-o-clock';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record->status === 'pending' && $record->due_date < now() ? 'danger' : null),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição da Fatura')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor Cobrado')
                    ->money('BRL')
                    ->sortable()
                    ->summarize(
                        Summarizer::make()
                            ->label('Total Histórico')
                            ->using(fn ($query) => $query->where('status', 'paid')->sum('amount'))
                            ->money('BRL')
                    ),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Data de Pagamento')
                    ->date('d/m/Y')
                    ->placeholder('-'),

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
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Situação')
                    ->options([
                        'pending' => 'Pendentes',
                        'paid' => 'Pagos',
                        'cancelled' => 'Cancelados',
                    ]),
            ])
            ->headerActions([
                // 🚀 Agora usamos a classe diretamente graças ao import
                CreateAction::make()
                    ->label('Lançar Fatura Manual')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['status'] = 'pending';
                        return $data;
                    }),
            ])
            ->actions([
                // 🚀 Agora usamos as classes diretamente graças aos imports
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                // 🚀 Agora usamos as classes diretamente graças aos imports
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('due_date', 'desc');
    }
}
