<?php

namespace App\Filament\Resources\GoodsReceipts\Tables;

use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GoodsReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['items.product']))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('receipt_number')
                    ->label('Canhoto / Nº')
                    ->searchable()
                    ->placeholder('S/N'),

                TextColumn::make('received_at')
                    ->label('Data de Chegada')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('items_summary')
                    ->label('Itens Comprados')
                    ->state(function ($record): string {
                        if ($record->items->isEmpty()) {
                            return 'Sem itens';
                        }

                        $labels = $record->items->map(function ($item) {
                            $qty = (float) ($item->raw_quantity ?: $item->quantity ?: 0);
                            $fmtQty = (floor($qty) == $qty)
                                ? number_format($qty, 0, ',', '.')
                                : rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',');
                            $uom = strtoupper((string) ($item->purchase_uom ?: $item->product?->unit_of_measure ?: 'UN'));
                            $name = $item->product?->name ?? "Item #{$item->product_id}";
                            return "{$fmtQty} {$uom} {$name}";
                        });

                        $shown = $labels->take(2)->implode(', ');
                        $extra = $labels->count() - 2;

                        return $extra > 0 ? "{$shown} (+{$extra})" : $shown;
                    })
                    ->tooltip(function ($record): ?string {
                        if ($record->items->count() <= 2) {
                            return null;
                        }

                        return $record->items->map(function ($item) {
                            $qty = (float) ($item->raw_quantity ?: $item->quantity ?: 0);
                            $uom = strtoupper((string) ($item->purchase_uom ?: $item->product?->unit_of_measure ?: 'UN'));
                            $name = $item->product?->name ?? "Item #{$item->product_id}";
                            return "• {$qty} {$uom} — {$name}";
                        })->implode("\n");
                    })
                    ->wrap(),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('BRL')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Status Fiscal')
                    ->badge()
                    ->formatStateUsing(fn (string $state, $record): string => $record?->payment_origin === 'retroactive' ? 'Histórico Retroativo' : match ($state) {
                        'pending_invoice' => 'Aguardando NF-e',
                        'reconciled'      => 'Conciliado (Com NF-e)',
                        'cancelled'       => 'Cancelado',
                        default           => $state,
                    })
                    ->color(fn (string $state, $record): string => $record?->payment_origin === 'retroactive' ? 'info' : match ($state) {
                        'pending_invoice' => 'warning',
                        'reconciled'      => 'success',
                        'cancelled'       => 'danger',
                        default           => 'gray',
                    }),
            ])
            ->defaultSort('received_at', 'desc')
            ->headerActions([
                Action::make('create_retroactive')
                    ->label('Nova Compra Retroativa')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->url(fn (): string => GoodsReceiptResource::getUrl('create', ['retroactive' => 1])),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status Fiscal')
                    ->options([
                        'retroactive'     => 'Histórico Retroativo',
                        'pending_invoice' => 'Aguardando NF-e',
                        'reconciled'      => 'Conciliado (Com NF-e)',
                        'cancelled'       => 'Cancelado',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $val = $data['value'] ?? null;
                        if (!$val) {
                            return $query;
                        }
                        if ($val === 'retroactive') {
                            return $query->where('payment_origin', 'retroactive');
                        }
                        return $query->where('status', $val)->where('payment_origin', '!=', 'retroactive');
                    }),

                SelectFilter::make('supplier_id')
                    ->label('Fornecedor')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('received_at')
                    ->form([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('received_at', '>=', $date))
                        ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('received_at', '<=', $date))
                    ),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }
}
