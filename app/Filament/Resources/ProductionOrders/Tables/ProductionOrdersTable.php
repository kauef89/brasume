<?php

namespace App\Filament\Resources\ProductionOrders\Tables;


use App\Enums\ProductionOrderStatus;
use Filament\Tables\Table;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Actions\EditAction;

class ProductionOrdersTable
{
    public static function getTableColumns(): array
    {
        return [
            Columns\TextColumn::make('due_date')
                ->date('d/m/Y')
                ->sortable()
                ->label('Data Alvo'),

            Columns\TextColumn::make('product.name')
                ->searchable()
                ->label('Produto'),

            Columns\TextColumn::make('planned_qty')
                ->numeric(3)
                ->label('Planejado'),

            Columns\TextColumn::make('actual_qty')
                ->numeric(3)
                ->label('Realizado'),

            Columns\TextColumn::make('status')
                ->label('Estado')
                ->badge(),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::getTableColumns())
            ->filters([
                Filters\SelectFilter::make('status')
                    ->options(ProductionOrderStatus::class),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->defaultSort('due_date', 'asc');
    }
}
