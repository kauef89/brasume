<?php

namespace App\Filament\Resources\CompanyAssets\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class CompanyAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('asset_tag')
                    ->label('Plaqueta')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('S/N'),

                TextColumn::make('name')
                    ->label('Descrição do Bem')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->location ? "Local: {$record->location}" : null),

                TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('acquisition_cost')
                    ->label('Custo Aquisição')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('acquisition_date')
                    ->label('Aquisição')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('warranty_until')
                    ->label('Garantia')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->warranty_until && $record->warranty_until->isPast() ? 'danger' : 'success')
                    ->placeholder('Sem garantia'),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'         => 'success',
                        'in_maintenance' => 'warning',
                        'written_off'    => 'danger',
                        default          => 'gray',
                    })
                    ->formatStateUsing(fn ($record) => $record->status_label),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options([
                        'active'         => 'Ativo / Em Operação',
                        'in_maintenance' => 'Em Manutenção',
                        'written_off'    => 'Baixado',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
