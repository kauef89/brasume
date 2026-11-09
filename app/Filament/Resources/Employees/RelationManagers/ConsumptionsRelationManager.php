<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\Grid as InfoGrid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConsumptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';
    protected static ?string $title = 'Consumo Interno & Lanches';
    protected static ?string $modelLabel = 'Pedido de Consumo';
    protected static ?string $pluralModelLabel = 'Consumos Internos';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Comanda / Pedido')
                    ->formatStateUsing(fn ($state) => "#{$state}")
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_summary')
                    ->label('Itens Consumidos')
                    ->state(function (Order $record) {
                        return $record->items->map(fn ($i) => "{$i->quantity}x {$i->product_name}")->implode(', ');
                    })
                    ->wrap()
                    ->limit(50)
                    ->placeholder('Nenhum item registrado'),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('BRL')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('source')
                    ->label('Origem')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'team_app'   => 'success',
                        'customer_app' => 'info',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'team_app'   => 'Lanche Equipe',
                        'cashier'    => 'Balcão / Caixa',
                        'pos_app'    => 'PDV Garçom',
                        default      => ucfirst($state ?? 'Interno'),
                    }),

                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Status Pagamento')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'paid'     => 'success',
                        'pending'  => 'warning',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'paid'     => 'Quitado / Faturado',
                        'pending'  => 'Fiado / Em Aberto',
                        default    => ucfirst($state ?? '-'),
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Situação do Pagamento')
                    ->options([
                        'paid'    => 'Quitado / Pago',
                        'pending' => 'Pendente (Fiado)',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
                    ),
            ])
            ->actions([
                Action::make('view_details')
                    ->label('Ver Itens')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading(fn (Order $record) => "Auditoria de Consumo - Pedido #{$record->id}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->infolist([
                        InfoGrid::make(3)->schema([
                            TextEntry::make('created_at')->label('Data/Hora')->dateTime('d/m/Y H:i'),
                            TextEntry::make('customer_name')->label('Identificação no Turno'),
                            TextEntry::make('total')->label('Valor Total')->money('BRL')->weight('bold'),
                        ]),
                        RepeatableEntry::make('items')
                            ->label('Produtos & Preparos')
                            ->schema([
                                TextEntry::make('product_name')->label('Produto')->weight('bold'),
                                TextEntry::make('quantity')->label('Qtd.'),
                                TextEntry::make('unit_price')->label('Preço Unitário')->money('BRL'),
                                TextEntry::make('total_price')->label('Total Item')->money('BRL')->weight('bold'),
                                TextEntry::make('notes')->label('Observação')->placeholder('-'),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }
}