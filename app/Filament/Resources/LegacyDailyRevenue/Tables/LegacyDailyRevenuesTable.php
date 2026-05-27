<?php

namespace App\Filament\Resources\LegacyDailyRevenueResource\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class LegacyDailyRevenuesTable
{
    public static function build(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("date")
                    ->label("Data do Faturamento")
                    ->date("d/m/Y")
                    ->sortable()
                    ->searchable(),

                TextColumn::make("total_revenue")
                    ->label("Total Faturado")
                    ->money("BRL")
                    ->weight("bold")
                    ->sortable(),

                TextColumn::make("order_count")
                    ->label("Qtd Pedidos")
                    ->numeric()
                    ->default("-")
                    ->sortable(),

                TextColumn::make("expenses")
                    ->label("Despesas/Custos")
                    ->money("BRL")
                    ->default(0)
                    ->sortable(),

                TextColumn::make("import_tag")
                    ->label("Tag Importação")
                    ->badge()
                    ->color("gray")
                    ->searchable(),
            ])
            ->defaultSort("date", "desc");
    }
}
