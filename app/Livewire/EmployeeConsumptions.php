<?php

namespace App\Livewire;

use App\Models\OrderItem;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

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
                        $query->select('orders.id')
                              ->from('orders')
                              ->where('orders.employee_id', $this->record->id)
                              ->where(function ($sub) {
                                  // 1. Pagamentos registrados no modelo novo
                                  $sub->whereExists(function ($pQuery) {
                                      $pQuery->select(DB::raw(1))
                                             ->from('payments')
                                             ->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
                                             ->whereColumn('payments.order_id', 'orders.id')
                                             ->where('payment_methods.engine_type', 'internal');
                                  })
                                  // 2. Fallback para registros legados
                                  ->orWhereExists(function ($opQuery) {
                                      $opQuery->select(DB::raw(1))
                                              ->from('order_payments')
                                              ->whereColumn('order_payments.order_id', 'orders.id')
                                              ->where('order_payments.payment_method', 'internal_consumption');
                                  });
                              });
                    })
            )
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
