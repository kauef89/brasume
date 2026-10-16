<?php

namespace App\Filament\Resources\ShoppingListItems\Tables;


use App\Enums\ShoppingListStatus;use Filament\Tables\Table;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Actions;
use App\Models\ShoppingListItem;

class ShoppingListItemsTable
{
    public static function getTableColumns(): array
    {
        return [
            Columns\TextColumn::make('product.name')
                ->searchable()
                ->sortable()
                ->label('Item / Insumo')
                ->description(fn ($record) => 'Tipo: ' . ucfirst($record->product?->type ?? 'Geral')),

            Columns\TextColumn::make('stock_situation')
                ->label('Estoque Atual / Mín')
                ->state(function (ShoppingListItem $record) {
                    $p = $record->product;
                    if (!$p) return '-';
                    $curr = number_format((float) $p->current_stock, 2, ',', '.');
                    $min = number_format((float) ($p->min_stock ?? 0), 2, ',', '.');
                    $uom = strtoupper($p->unit_of_measure ?? 'UN');
                    return "{$curr} / {$min} {$uom}";
                })
                ->badge()
                ->color(fn ($record) => ((float) ($record->product?->current_stock ?? 0) <= 0) ? 'danger' : 'warning'),

            Columns\TextColumn::make('suggested_qty')
                ->numeric(3)
                ->label('Qtd. Sugerida')
                ->sortable()
                ->formatStateUsing(fn ($state, $record) => number_format((float)$state, 2, ',', '.') . ' ' . strtoupper($record->product?->unit_of_measure ?? 'UN')),

            Columns\TextColumn::make('supplier.trade_name')
                ->searchable()
                ->placeholder('Sem Fornecedor Fixo')
                ->label('Fornecedor Preferencial'),

            Columns\BadgeColumn::make('urgency_level')
                ->colors([
                    'danger'  => 'critical',
                    'warning' => 'manual',
                    'success' => 'normal',
                ])
                ->formatStateUsing(fn (string $state) => match ($state) {
                    'critical' => 'Ruptura (Crítico)',
                    'manual'   => 'Manual',
                    default    => 'Normal',
                })
                ->label('Urgência'),

            Columns\BadgeColumn::make('status')
                ->colors([
                    'warning' => 'pending',
                    'primary' => 'ordered',
                    'success' => 'received',
                    'danger'  => 'cancelled',
                ])
                ->formatStateUsing(fn (string $state) => match ($state) {
                    'pending'   => 'Pendente',
                    'ordered'   => 'Pedido Feito',
                    'received'  => 'Recebido',
                    'cancelled' => 'Cancelado',
                    default     => ucfirst($state),
                })
                ->label('Status'),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::getTableColumns())
            ->filters([
                Filters\SelectFilter::make('status')
                    ->options(ShoppingListStatus::class)
                    ->default('pending'),

                Filters\SelectFilter::make('urgency_level')
                    ->label('Urgência')
                    ->options([
                        'critical' => 'Ruptura (Crítico)',
                        'manual'   => 'Manual',
                        'normal'   => 'Normal',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
            ]);
    }
}
