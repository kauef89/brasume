<?php

namespace App\Filament\Resources\ShoppingListItems\Schemas;


use App\Enums\ShoppingListStatus;use Filament\Schemas\Schema;
use Filament\Forms\Components;

class ShoppingListItemForm
{
    public static function getFormSchema(): array
    {
        return [
            Components\Select::make('product_id')
                ->relationship('product', 'name')
                ->searchable()
                ->required()
                ->label('Produto'),

            Components\Select::make('supplier_id')
                ->relationship('supplier', 'trade_name')
                ->searchable()
                ->label('Fornecedor Destino'),

            Components\TextInput::make('suggested_qty')
                ->numeric()
                ->required()
                ->label('Qtd. Sugerida'),

            Components\TextInput::make('approved_qty')
                ->numeric()
                ->label('Qtd. Aprovada'),

            Components\TextInput::make('estimated_cost')
                ->numeric()
                ->prefix('R$')
                ->label('Custo Estimado'),

            Components\Select::make('urgency_level')
                ->options([
                    'normal' => 'Normal (Reposição)',
                    'manual' => 'Adicionado Manualmente',
                    'critical' => 'Crítico (Falta no PDV)',
                ])
                ->default('normal')
                ->required()
                ->label('Urgência'),

            Components\Select::make('status')
                ->options(ShoppingListStatus::class)
                ->default('pending')
                ->required()
                ->label('Status'),

            Components\Textarea::make('notes')
                ->columnSpanFull()
                ->label('Observações'),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
