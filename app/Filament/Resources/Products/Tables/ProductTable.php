<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;
use App\Models\Category;
use App\Models\Product;
use App\Filament\Resources\Products\ProductResource;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Components as FormComponents;
use App\Services\ProductCostingService;

class ProductTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Product $record): string => ProductResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Produto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match (strtolower($state)) {
                        'dish' => 'Prato',
                        'combo' => 'Combo',
                        'resale' => 'Revenda',
                        'ingredient' => 'Insumo',
                        'manufactured' => 'Preparado',
                        default => ucfirst($state),
                    })
                    ->color('gray'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoria')
                    ->default('Sem Categoria')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('dine_in_price')
                    ->label('Preço Salão')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('lucratividade')
                    ->label('Margem')
                    ->state(function (Product $record) {
                        if (in_array($record->type, ['ingredient', 'supply', 'internal'])) return null;

                        $data = ProductCostingService::getMarginData($record);
                        return $data['margin_percentage'] ?? 0;
                    })
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float)$state, 2, ',', '.') . '%' : '-')
                    ->badge()
                    ->color(function ($state) {
                        if ($state === null) return 'gray';
                        $pct = (float) $state;
                        if ($pct >= 30) return 'success';
                        if ($pct >= 15) return 'warning';
                        return 'danger';
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'gray' => 'available',
                        'warning' => 'out_of_stock',
                        'danger' => 'archived'
                    ])
                    ->formatStateUsing(fn (string $state): string => match (strtolower($state)) {
                        'available' => 'Disponível',
                        'out_of_stock' => 'Esgotado',
                        'archived' => 'Arquivado',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('connection_status')
                    ->label('Cadeia de custos')
                    ->state(function (Product $record) {
                        // Verifica validação para Pratos
                        if ($record->type === 'dish') {
                            return $record->technicalSheets()->exists() ? 'Conectado' : 'Desconectado';
                        }

                        // Verifica validação para Revendas
                        if ($record->type === 'resale') {
                            return $record->costHistories()->exists() ? 'Conectado' : 'Desconectado';
                        }

                        // Para Insumos e Preparados, podemos assumir que estão conectados
                        // ou retornar `null` se você quiser esconder a badge para eles.
                        return 'Conectado';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Conectado' => 'gray',
                        'Desconectado' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Conectado' => 'heroicon-m-link',
                        'Desconectado' => 'heroicon-m-link-slash',
                        default => 'heroicon-m-link',
                    })
                    ->tooltip(function (Product $record, $state) {
                        if ($state === 'Desconectado') {
                            if ($record->type === 'dish') {
                                return 'Este prato precisa estar associado a uma Ficha Técnica para o cálculo de custo e baixa automática de estoque.';
                            }
                            if ($record->type === 'resale') {
                                return 'Este produto precisa estar associado a uma Nota de Compra para o cálculo do custo médio.';
                            }
                        }

                        // Tooltip extra para os que estão corretos!
                        if ($state === 'Conectado') {
                            return 'Cadeia de custos validada e configurada corretamente.';
                        }

                        return null;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categorias')
                    ->relationship('category', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options([
                        'available' => 'Disponível',
                        'out_of_stock' => 'Esgotado',
                        'archived' => 'Arquivado'
                    ]),
            ])
            ->actions([])
            ->bulkActions([
                // 🚀 Bulk Actions usando o namespace correto de Tables
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),

                    Actions\BulkAction::make('alterarCategoria')
                        ->label('Alterar Categoria')
                        ->icon('heroicon-o-tag')
                        ->form([
                            FormComponents\Select::make('category_id')
                                ->label('Nova Categoria')
                                ->options(fn () => Category::pluck('name', 'id'))
                                ->required()
                        ])
                        ->action(fn(Collection $records, array $data) => $records->each->update(['category_id' => $data['category_id']])),

                    Actions\BulkAction::make('alterarStatus')
                        ->label('Alterar Status')
                        ->icon('heroicon-o-check-circle')
                        ->form([
                            FormComponents\Select::make('status')
                                ->label('Novo Status')
                                ->options([
                                    'available' => 'Disponível',
                                    'out_of_stock' => 'Esgotado',
                                    'archived' => 'Arquivado'
                                ])
                                ->required()
                        ])
                        ->action(fn(Collection $records, array $data) => $records->each->update(['status' => $data['status']])),
                ]),
            ]);
    }
}
