<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\Order;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeliveriesRelationManager extends RelationManager
{
    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return method_exists($ownerRecord, 'isCourier') ? $ownerRecord->isCourier() : true;
    }
    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Histórico de Corridas';

    protected static ?string $modelLabel = 'Corrida';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Pedido')
                    ->formatStateUsing(fn ($state) => "#{$state}")
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('Consumidor Balcão/Mesa')
                    ->searchable(),

                Tables\Columns\TextColumn::make('deliveryAddress.neighborhood.name')
                    ->label('Bairro')
                    ->placeholder('-')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total Pedido')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_distance_km')
                    ->label('Distância')
                    ->suffix(' km')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('courier_fee')
                    ->label('Taxa do Motoboy')
                    ->money('BRL')
                    ->weight('black')
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status Pedido')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        'dispatched' => 'info',
                        default => 'warning',
                    }),

                Tables\Columns\IconColumn::make('courier_settled_at')
                    ->label('Acertado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até')->default(now()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ]);
    }
}
