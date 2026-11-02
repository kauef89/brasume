<?php

namespace App\Livewire;

use App\Models\Employee;
use App\Models\OrderItem;
use App\Models\Customer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class EmployeeConsumptions extends BaseWidget
{
    public ?Employee $record = null;

    public function table(Table $table): Table
    {
        $cleanCpf = preg_replace('/\D/', '', $this->record->cpf ?? '');
        $phone = $this->record->phone;
        $name = $this->record->name;

        // Resolve os IDs de cliente vinculados a este colaborador
        $customerIds = Customer::where(function ($q) use ($cleanCpf, $phone) {
                if (!empty($cleanCpf)) $q->where('cpf', $cleanCpf);
                if (!empty($phone)) $q->orWhere('phone', $phone);
            })
            ->pluck('id')
            ->toArray();

        return $table
            ->query(
                OrderItem::query()
                    ->whereIn('order_id', function ($query) use ($customerIds, $name) {
                        $query->select('orders.id')
                            ->from('orders')
                            ->where(function ($q) use ($customerIds, $name) {
                                if (!empty($customerIds)) {
                                    $q->whereIn('orders.customer_id', $customerIds);
                                }
                                $q->orWhere('orders.customer_name', 'like', "Lanche: {$name}%")
                                  ->orWhere('orders.customer_name', 'like', "%{$name}%");
                            })
                            ->where(function ($sub) {
                                $sub->whereExists(function ($pQuery) {
                                    $pQuery->select(DB::raw(1))
                                        ->from('payments')
                                        ->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
                                        ->whereColumn('payments.order_id', 'orders.id')
                                        ->whereIn('payment_methods.engine_type', ['internal', 'allowance']);
                                })
                                ->orWhere('orders.source', 'team_app');
                            });
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order.id')
                    ->label('Pedido')
                    ->formatStateUsing(fn ($state) => "#{$state}"),
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Item Consumido')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order.customer_name')
                    ->label('Identificação na Comanda'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
