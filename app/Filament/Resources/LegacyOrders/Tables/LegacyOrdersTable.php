<?php

namespace App\Filament\Resources\LegacyOrders\Tables;


use App\Enums\OrderType;use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use Illuminate\Database\Eloquent\Builder;

class LegacyOrdersTable
{
    public static function build(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("import_tag")
                    ->label("Tag Importação")
                    ->badge()
                    ->color("gray")
                    ->searchable()
                    ->sortable(),

                TextColumn::make("issue_date")
                    ->label("Data")
                    ->dateTime("d/m/Y H:i")
                    ->sortable(),

                TextColumn::make("order_type")
                    ->label("Tipo")
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            "delivery" => "Delivery",
                            "counter" => "Balcão",
                            "table" => "Mesa",
                            default => $state,
                        },
                    ),

                // 🚀 A CORREÇÃO ESTÁ AQUI: Substituímos o array do searchable por uma Closure explícita
                TextColumn::make("customer_name")
                    ->label("Cliente")
                    ->state(function ($record) {
                        if ($record->customer_id) {
                            return $record->customer?->name ?? "Consumidor Final";
                        }
                        if ($record->legacy_customer_id) {
                            return $record->legacyCustomer?->legacy_name ?? "Consumidor Final";
                        }
                        return "Consumidor Final";
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        // O encapsulamento num where() isola o "OR" do resto da query da tabela
                        return $query->where(function ($q) use ($search) {
                            $q->whereHas('customer', function ($q2) use ($search) {
                                $q2->where('name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('legacyCustomer', function ($q3) use ($search) {
                                $q3->where('legacy_name', 'like', "%{$search}%");
                            });
                        });
                    }),

                TextColumn::make("total_value")
                    ->label("Total (Itens)")
                    ->money("BRL")
                    ->sortable(),
            ])
            ->defaultSort("issue_date", "desc")
            ->filters([
                SelectFilter::make("order_type")
                    ->label("Tipo de Pedido")
                    ->options(OrderType::class),
            ])
            ->actions([ViewAction::make()]);
    }
}
