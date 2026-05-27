<?php

namespace App\Filament\Resources\LegacyCustomers\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Tables\Filters\TernaryFilter;

class LegacyCustomersTable
{
    public static function getTableColumns(): array
    {
        return [
            TextColumn::make("legacy_name")
                ->label("Nome no CSV")
                ->searchable()
                ->sortable(),

            TextColumn::make("customer.name")
                ->label("Cliente Mapeado (V3)")
                ->searchable()
                ->sortable()
                ->badge()
                ->color(fn ($record) => $record->customer_id ? 'success' : 'danger')
                ->default('Não Mapeado'),
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::getTableColumns())
            ->filters([
                TernaryFilter::make("is_mapped")
                    ->label("Status do Mapeamento")
                    ->placeholder("Todos")
                    ->trueLabel("Mapeados")
                    ->falseLabel("Não Mapeados")
                    ->queries(
                        true: fn($query) => $query->whereNotNull("customer_id"),
                        false: fn($query) => $query->whereNull("customer_id"),
                    ),
            ])
            ->actions([EditAction::make()->modalWidth("md")]);
    }
}
