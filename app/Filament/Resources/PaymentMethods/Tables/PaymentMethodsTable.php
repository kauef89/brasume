<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;

class PaymentMethodsTable 
{
    public static function configure(Table $table): Table 
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Forma de Pagamento')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('destinationAccount.name')
                ->label('Conta Destino')
                ->default('Sem Vínculo (Perda/Cortesia)')
                ->badge()
                ->color('success'),
            Tables\Columns\TextColumn::make('engine_type')->label('Comportamento')->badge(),
            Tables\Columns\ToggleColumn::make('show_on_pos')->label('PDV?'),
            Tables\Columns\ToggleColumn::make('show_on_delivery')->label('Delivery?'),
            Tables\Columns\ToggleColumn::make('is_active')->label('Ativo?'),
        ])->recordActions([
            Actions\EditAction::make(),
        ]);
    }
}