<?php

namespace App\Filament\Resources\LegacyModifiers\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;

class LegacyModifiersTable
{
    public static function build(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Sabor / Adicional (Legado)')
                    ->searchable(),

                TextColumn::make('order_item_modifiers_count')
                    ->counts('orderItemModifiers')
                    ->label('Ocorrências')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('mapped_to')
                    ->label('Mapeado Para')
                    ->badge()
                    ->color(fn ($record) => ($record->modifier_option_id || $record->current_product_id) ? 'gray' : ($record->is_ignored ? 'gray' : 'danger'))
                    ->state(function ($record) {
                        if ($record->is_ignored) return 'Ignorado';
                        if ($record->modifier_option_id) return 'Opção: ' . ($record->currentOption->name ?? '');
                        if ($record->current_product_id) return 'Produto: ' . ($record->currentProduct->name ?? '');
                        return 'Pendente';
                    }),
            ])
            ->defaultSort('modifier_option_id', 'asc')
            ->filters([
                TernaryFilter::make('is_mapped')
                    ->label('Status')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('modifier_option_id')->orWhereNotNull('current_product_id'),
                        false: fn ($query) => $query->whereNull('modifier_option_id')->whereNull('current_product_id')->where('is_ignored', false),
                    )
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth('md')
                    ->mutateFormDataUsing(function (array $data): array {
                        if (!isset($data['mapping_type'])) return $data;
                        if ($data['mapping_type'] === 'option') {
                            $data['current_product_id'] = null;
                        } else {
                            $data['modifier_option_id'] = null;
                        }
                        return $data;
                    }),
            ]);
    }
}