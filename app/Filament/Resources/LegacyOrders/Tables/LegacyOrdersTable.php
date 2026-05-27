<?php

namespace App\Filament\Resources\LegacyOrders\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;

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

                // 🚀 ESTADO COMPUTADO BLINDADO COM NULLSAFE (?->) E COALESCING (??)
                TextColumn::make("customer_name")
                    ->label("Cliente")
                    ->state(function ($record) {
                        if ($record->customer_id) {
                            return $record->customer?->name ??
                                "Consumidor Final";
                        }
                        if ($record->legacy_customer_id) {
                            return $record->legacyCustomer?->legacy_name ??
                                "Consumidor Final";
                        }
                        return "Consumidor Final";
                    })
                    ->searchable([
                        "customer.name",
                        "legacyCustomer.legacy_name",
                    ]),

                TextColumn::make("total_value")
                    ->label("Total (Itens)")
                    ->money("BRL")
                    ->sortable(),
            ])
            ->defaultSort("issue_date", "desc")
            ->filters([
                SelectFilter::make("order_type")
                    ->label("Tipo de Pedido")
                    ->options([
                        "delivery" => "Delivery",
                        "counter" => "Balcão", // 🚀 Corrigido para bater com os dados do banco!
                        "table" => "Mesa", // 🚀 Corrigido para bater com os dados do banco!
                    ]),
            ])
            ->actions([ViewAction::make()]);
    }
}
