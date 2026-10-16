<?php

namespace App\Filament\Resources\ProductModifiers\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Product;
use App\Models\Category;

class ProductModifierTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ingredient' => 'Ingrediente',
                        'add_on' => 'Adicional',
                        'observation' => 'Observação',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'ingredient' => 'info',
                        'add_on' => 'success',
                        'observation' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                // 🚀 RADAR 1: Quantidade de Categorias Únicas Atingidas
                Tables\Columns\TextColumn::make('categories_count')
                    ->label('Categorias')
                    ->state(function ($record) {
                        $productIds = DB::table('modifier_group_product')
                            ->where('modifier_group_id', $record->id)
                            ->pluck('product_id');

                        if ($productIds->isEmpty()) {
                            return 0;
                        }

                        return Product::whereIn('id', $productIds)
                            ->whereNotNull('category_id')
                            ->distinct('category_id')
                            ->count('category_id');
                    })
                    ->badge()
                    ->color('gray')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            DB::table('modifier_group_product')
                                ->join('products', 'products.id', '=', 'modifier_group_product.product_id')
                                ->whereColumn('modifier_group_product.modifier_group_id', 'modifier_groups.id')
                                ->whereNotNull('products.category_id')
                                ->selectRaw('count(distinct products.category_id)'),
                            $direction
                        );
                    }),

                // 🚀 RADAR 2: Total de Produtos
                Tables\Columns\TextColumn::make('products_count')
                    ->counts('products')
                    ->label('Produtos')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                // 🚀 RADAR 3: Itens com is_active = false dentro deste grupo
                Tables\Columns\TextColumn::make('inactive_options_count')
                    ->label('Inativos')
                    ->state(function ($record) {
                        return DB::table('modifier_options')
                            ->where('modifier_group_id', $record->id)
                            ->where('is_active', false)
                            ->count();
                    })
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'danger' : 'gray')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            DB::table('modifier_options')
                                ->whereColumn('modifier_options.modifier_group_id', 'modifier_groups.id')
                                ->where('modifier_options.is_active', false)
                                ->selectRaw('count(*)'),
                            $direction
                        );
                    }),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Ativo')
                    ->sortable(),
            ])
            ->filters([
                // 1. Filtro por Tipo de Modificador
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'ingredient' => 'Ingrediente',
                        'add_on' => 'Adicional',
                        'observation' => 'Observação',
                    ])
                    ->multiple(),

                // 2. Filtro por Status (Ativo por padrão)
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status do Grupo')
                    ->trueLabel('Apenas Ativos')
                    ->falseLabel('Apenas Inativos')
                    ->default(true),

                // 3. Filtro por Categoria de Produtos Vinculados
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoria do Cardápio')
                    ->options(fn () => Category::where('type', 'external')->pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereExists(function ($sub) use ($data) {
                            $sub->select(DB::raw(1))
                                ->from('modifier_group_product')
                                ->join('products', 'products.id', '=', 'modifier_group_product.product_id')
                                ->whereColumn('modifier_group_product.modifier_group_id', 'modifier_groups.id')
                                ->where('products.category_id', $data['value']);
                        });
                    })
                    ->searchable()
                    ->preload(),

                // 4. Atalho: Grupos que possuem opções desativadas
                Tables\Filters\Filter::make('has_inactive_options')
                    ->label('Com opções inativas')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('options', fn ($q) => $q->where('is_active', false))),

                // 5. Atalho: Grupos órfãos (sem nenhum produto vinculado)
                Tables\Filters\Filter::make('unlinked')
                    ->label('Sem produtos vinculados')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereDoesntHave('products')),
            ])
            ->recordActions([
                Actions\EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
