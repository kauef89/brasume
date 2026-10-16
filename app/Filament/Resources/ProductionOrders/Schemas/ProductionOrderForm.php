<?php

namespace App\Filament\Resources\ProductionOrders\Schemas;


use App\Enums\ProductionOrderStatus;use Filament\Schemas\Schema;
use Filament\Forms\Components;

class ProductionOrderForm
{
    public static function getFormSchema(): array
    {
        return [
            Components\Select::make('product_id')
                ->relationship('product', 'name')
                ->searchable()
                ->required()
                ->label('Produto Manufaturado'),

            Components\DatePicker::make('due_date')
                ->required()
                ->label('Data Alvo'),

            Components\TextInput::make('planned_qty')
                ->numeric()
                ->required()
                ->label('Qtd. Planejada'),

            Components\TextInput::make('actual_qty')
                ->numeric()
                ->label('Qtd. Produzida Real'),

            Components\Select::make('status')
                ->options(ProductionOrderStatus::class)
                ->default('pending')
                ->required()
                ->label('Status'),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
