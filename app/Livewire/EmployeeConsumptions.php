<?php

namespace App\Livewire;

use App\Models\OrderItem;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;

class EmployeeConsumptions extends BaseWidget
{
    public $record;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderItem::query()
                    ->whereIn('order_id', function ($query) {
                        $query->select('id')
                              ->from('orders')
                              ->where('employee_id', $this->record->id)
                              ->whereIn('id', function ($q) {
                                  $q->select('order_id')
                                    ->from('order_payments')
                                    ->where('payment_method', 'internal_consumption');
                              });
                    })
            )
            // Fixamos o título em português aqui para o Filament não tentar adivinhar!
            ->heading('Histórico de Consumo')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                    
                TextColumn::make('product_name')
                    ->label('Item Consumido')
                    ->searchable(),
                    
                TextColumn::make('quantity')
                    ->label('Qtd')
                    ->numeric(),
                    
                TextColumn::make('total_price')
                    ->label('Valor Total')
                    ->money('BRL')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}