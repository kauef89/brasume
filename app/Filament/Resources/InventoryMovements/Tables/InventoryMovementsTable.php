<?php

namespace App\Filament\Resources\InventoryMovements\Tables;

use App\Models\InventoryMovement;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'storageLocation']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('product.name')
                    ->label('Insumo / Produto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Operação')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in'         => 'Entrada (+)',
                        'out'        => 'Saída (-)',
                        'loss'       => 'Perda / Quebra',
                        'adjustment' => 'Ajuste Balanço',
                        default      => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'in'         => 'success',
                        'out'        => 'info',
                        'loss'       => 'danger',
                        'adjustment' => 'warning',
                        default      => 'gray',
                    }),

                TextColumn::make('quantity')
                    ->label('Qtd. Movimentada')
                    ->color(fn ($state) => (float) $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(function ($state, InventoryMovement $record) {
                        $val = (float) $state;
                        $prefix = $val > 0 ? '+' : '';
                        $uom = $record->product?->unit_of_measure;
                        $uomStr = is_object($uom) && method_exists($uom, 'value') ? $uom->value : (string) ($uom ?? 'UN');
                        return $prefix . number_format($val, 3, ',', '.') . ' ' . $uomStr;
                    })
                    ->sortable(),

                TextColumn::make('balance_after')
                    ->label('Saldo Pós-Mov.')
                    ->formatStateUsing(function ($state, InventoryMovement $record) {
                        $val = (float) $state;
                        $uom = $record->product?->unit_of_measure;
                        $uomStr = is_object($uom) && method_exists($uom, 'value') ? $uom->value : (string) ($uom ?? 'UN');
                        return number_format($val, 3, ',', '.') . ' ' . $uomStr;
                    })
                    ->weight('black'),

                TextColumn::make('unit_cost')
                    ->label('Custo Unit.')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->label('Origem / Motivo')
                    ->searchable()
                    ->wrap()
                    ->limit(45),

                TextColumn::make('storageLocation.name')
                    ->label('Local Armazenado')
                    ->placeholder('Padrão / Geral')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo de Operação')
                    ->options([
                        'in'         => 'Entrada (+)',
                        'out'        => 'Saída (-)',
                        'loss'       => 'Perda / Quebra',
                        'adjustment' => 'Ajuste Balanço',
                    ]),

                SelectFilter::make('product_id')
                    ->label('Produto / Insumo')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('storage_location_id')
                    ->label('Local de Armazenamento')
                    ->relationship('storageLocation', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('Movimentações De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
                    ),
            ]);
    }
}