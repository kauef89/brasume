<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductPriceLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PriceLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'priceLogs';
    protected static ?string $title = 'Histórico de Preços e Vendas';
    protected static ?string $modelLabel = 'Registro de Preço';
    protected static ?string $pluralModelLabel = 'Histórico de Preços';
    protected static string | \BackedEnum | null $icon = 'heroicon-o-chart-bar';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('price')
            ->defaultSort('started_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('channel')
                    ->label('Aplicação')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'dine_in'              => 'Consumo no Local',
                        'takeaway', 'delivery' => 'Para Viagem',
                        default                => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'dine_in'              => 'info',
                        'takeaway', 'delivery' => 'warning',
                        default                => 'gray',
                    }),

                Tables\Columns\TextColumn::make('vigencia')
                    ->label('Período de Vigência')
                    ->state(function (ProductPriceLog $record): string {
                        $start = $record->started_at->format('d/m/y H:i');
                        if (!$record->ended_at) {
                            return "Desde {$start} (Vigente)";
                        }
                        return "{$start} até " . $record->ended_at->format('d/m/y H:i');
                    })
                    ->badge()
                    ->color(fn ($record) => $record->ended_at === null ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Preço Praticado')
                    ->money('BRL')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('cost')
                    ->label('CMV da Condição')
                    ->money('BRL')
                    ->color('gray')
                    ->description(fn (ProductPriceLog $record) => $record->channel === 'dine_in' ? 'Sem embalagem' : 'Com embalagem'),

                Tables\Columns\TextColumn::make('margin_percentage')
                    ->label('Margem')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1, ',', '.') . '%')
                    ->badge()
                    ->color(fn ($state) => (float) $state >= 30 ? 'success' : ((float) $state >= 15 ? 'warning' : 'danger')),

                // 🚀 MÉTRICAS DE VENDAS DO PERÍODO
                Tables\Columns\TextColumn::make('sales_qty')
                    ->label('Qtd. Vendida')
                    ->state(function (ProductPriceLog $record) {
                        $m = $record->getSalesMetrics();
                        return number_format($m['total_qty'], 0, ',', '.') . ' un';
                    })
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('sales_revenue')
                    ->label('Faturamento')
                    ->state(fn (ProductPriceLog $record) => 'R$ ' . number_format($record->getSalesMetrics()['total_revenue'], 2, ',', '.'))
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('gross_profit')
                    ->label('Lucro Bruto Real')
                    ->state(fn (ProductPriceLog $record) => 'R$ ' . number_format($record->getSalesMetrics()['gross_contribution'], 2, ',', '.'))
                    ->color('success')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('trigger_reason')
                    ->label('Motivo')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'price_change'     => 'Ajuste de Preço',
                        'recipe_update'    => 'Alteração Receita',
                        'initial_baseline' => 'Marco Zero',
                        default            => $state ?? 'Manual',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->label('Modo de Consumo')
                    ->options([
                        'dine_in'  => 'Consumo no Local',
                        'takeaway' => 'Para Viagem',
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return in_array($ownerRecord->type, ['dish', 'combo', 'resale']);
    }
}
