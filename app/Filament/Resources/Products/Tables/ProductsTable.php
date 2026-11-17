<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Filament\Resources\Products\Actions\MergeProductsBulkAction;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\AvailabilityService;
use App\Services\Catalog\ProductCostingService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components as FormComponents;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Product $record): string => ProductResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Produto')
                    ->searchable()
                    ->sortable()
                    ->grow(true)
                    ->wrap(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable()
                    ->grow(false),

                Tables\Columns\SelectColumn::make('category_id')
                    ->label('Categoria')
                    ->options(function () {
                        return Category::query()
                            ->with('parent')
                            ->get()
                            ->sortBy(fn ($cat) => ($cat->parent?->name ?? $cat->name) . ' ' . $cat->name)
                            ->mapWithKeys(function (Category $category) {
                                $label = $category->parent
                                    ? "{$category->parent->name} ➔ {$category->name}"
                                    : "📁 {$category->name}";

                                return [$category->id => $label];
                            })
                            ->toArray();
                    })
                    ->placeholder('Sem Categoria')
                    ->sortable()
                    ->selectablePlaceholder(false)
                    ->grow(true),

                Tables\Columns\TextInputColumn::make('dine_in_price')
                    ->label('Preço Salão')
                    ->type('number')
                    ->step('0.01')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->sortable()
                    ->grow(false)
                    ->width('1%'),

                Tables\Columns\TextColumn::make('lucratividade')
                    ->label('Margem')
                    ->state(function (Product $record) {
                        $typeVal = $record->type instanceof ProductType ? $record->type->value : (string) $record->type;

                        if (in_array($typeVal, [
                            ProductType::INGREDIENT->value,
                            ProductType::CONSUMABLE->value,
                            ProductType::PACKAGING->value,
                            ProductType::MANUFACTURED->value,
                            ProductType::COMBO->value,
                        ], true)) {
                            return null;
                        }

                        $data = ProductCostingService::getMarginData($record);
                        return $data['margin_percentage'] ?? 0;
                    })
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 2, ',', '.') . '%' : '-')
                    ->badge()
                    ->color(function ($state) {
                        if ($state === null) return 'gray';
                        $pct = (float) $state;
                        if ($pct >= 30) return 'success';
                        if ($pct >= 15) return 'warning';
                        return 'danger';
                    })
                    ->grow(false),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Estoque')
                    ->state(fn (Product $record): ?float => $record->track_inventory ? (float) $record->current_stock : null)
                    ->formatStateUsing(function ($state, Product $record): string {
                        if ($state === null) {
                            return '-';
                        }

                        $qty = (float) $state;
                        $formatted = fmod($qty, 1.0) === 0.0
                            ? number_format($qty, 0, ',', '.')
                            : rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',');

                        $unitRaw = $record->unit ?? null;
                        $unit = $unitRaw instanceof \BackedEnum
                            ? $unitRaw->value
                            : (is_string($unitRaw) ? trim($unitRaw) : '');

                        return $unit !== '' ? "{$formatted} {$unit}" : $formatted;
                    })
                    ->badge()
                    ->color(function ($state, Product $record): string {
                        if ($state === null) {
                            return 'gray';
                        }

                        $qty = (float) $state;
                        if ($qty <= 0) {
                            return 'danger';
                        }

                        $minStock = $record->min_stock ?? $record->minimum_stock ?? null;
                        if ($minStock !== null && (float) $minStock > 0 && $qty <= (float) $minStock) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->tooltip(function (Product $record): string {
                        if (!$record->track_inventory) {
                            return 'Controle de estoque direto desativado (produção sob demanda ou combo).';
                        }

                        $minStock = $record->min_stock ?? $record->minimum_stock ?? null;
                        if ($minStock !== null && (float) $minStock > 0) {
                            return 'Estoque mínimo configurado: ' . number_format((float) $minStock, 2, ',', '.');
                        }

                        return 'Saldo atual controlado pelo Kardex.';
                    })
                    ->sortable()
                    ->grow(false),

                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options(ProductStatus::class)
                    ->selectablePlaceholder(false)
                    ->sortable()
                    ->rules(['required'])
                    ->grow(false)
                    ->width('155px')
                    ->updateStateUsing(function ($record, $state, $livewire) {
                        if ($state === 'available' && $record->track_inventory && (float) $record->current_stock <= 0) {
                            Notification::make()
                                ->warning()
                                ->title('Estoque Zerado')
                                ->body("Você não pode colocar '{$record->name}' como disponível sem dar entrada no saldo.")
                                ->send();

                            $livewire->dispatch('$refresh');
                            return;
                        }

                        try {
                            app(AvailabilityService::class)->updateAvailability($record->id, $state);

                            Notification::make()
                                ->success()
                                ->title('Disponibilidade Atualizada')
                                ->body("Status de '{$record->name}' atualizado com cascata e propagação em tempo real.")
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Erro ao Atualizar')
                                ->body($e->getMessage())
                                ->send();
                        }

                        $livewire->dispatch('$refresh');
                    }),

                // 🚀 COLUNA CADEIA DE CUSTOS MULTI-NÍVEL (Oculta status de ficha técnica para Combos)
                Tables\Columns\TextColumn::make('connection_status')
                    ->label('Cadeia de Custos')
                    ->state(function (Product $record): ?\App\Enums\CostChainStatus {
                        $typeVal = $record->type instanceof ProductType ? $record->type->value : (string) $record->type;

                        if ($typeVal === ProductType::COMBO->value) {
                            return null;
                        }

                        return $record->cost_chain_status;
                    })
                    ->placeholder('-')
                    ->badge()
                    ->tooltip(function (Product $record): ?string {
                        $typeVal = $record->type instanceof ProductType ? $record->type->value : (string) $record->type;

                        if ($typeVal === ProductType::COMBO->value) {
                            return 'Combo: custo dinâmico calculado a partir dos itens escolhidos em cada passo.';
                        }

                        $eval = $record->cost_chain_evaluation;
                        if (!empty($eval['issues'])) {
                            return implode("\n• ", array_merge(['Pendências encontradas:'], $eval['issues']));
                        }
                        return $eval['status']->getDescription();
                    })
                    ->grow(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categorias')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(function (): array {
                        $categories = Category::query()
                            ->get(['id', 'name', 'parent_id'])
                            ->keyBy('id');

                        $buildPath = function (Category $cat) use ($categories): string {
                            $names = [$cat->name];
                            $visited = [$cat->id => true];
                            $curr = $cat;

                            while ($curr->parent_id && isset($categories[$curr->parent_id]) && !isset($visited[$curr->parent_id])) {
                                $curr = $categories[$curr->parent_id];
                                $visited[$curr->id] = true;
                                array_unshift($names, $curr->name);
                            }

                            return count($names) > 1
                                ? implode(' ➔ ', $names)
                                : "📁 {$cat->name}";
                        };

                        return $categories
                            ->mapWithKeys(fn (Category $cat) => [$cat->id => $buildPath($cat)])
                            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $selectedIds = array_filter(
                            array_map('intval', $data['values'] ?? (!empty($data['value']) ? [$data['value']] : [])),
                            fn (int $id) => $id > 0
                        );

                        if (empty($selectedIds)) {
                            return $query;
                        }

                        $childrenByParent = [];
                        foreach (Category::query()->get(['id', 'parent_id']) as $cat) {
                            if ($cat->parent_id) {
                                $childrenByParent[(int) $cat->parent_id][] = (int) $cat->id;
                            }
                        }

                        $resolvedIds = [];
                        $stack = $selectedIds;

                        while (!empty($stack)) {
                            $currentId = array_pop($stack);
                            if (isset($resolvedIds[$currentId])) {
                                continue;
                            }

                            $resolvedIds[$currentId] = true;

                            foreach ($childrenByParent[$currentId] ?? [] as $childId) {
                                if (!isset($resolvedIds[$childId])) {
                                    $stack[] = $childId;
                                }
                            }
                        }

                        return $query->whereIn('products.category_id', array_keys($resolvedIds));
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options(ProductStatus::class),

                Tables\Filters\SelectFilter::make('stock_filter')
                    ->label('Estoque')
                    ->options([
                        'zero'      => 'Sem Saldo (<= 0)',
                        'positive'  => 'Com Saldo (> 0)',
                        'tracked'   => 'Com Controle de Estoque',
                        'untracked' => 'Sem Controle (Sob Demanda)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'zero'      => $query->where('track_inventory', true)->where('current_stock', '<=', 0),
                            'positive'  => $query->where('track_inventory', true)->where('current_stock', '>', 0),
                            'tracked'   => $query->where('track_inventory', true),
                            'untracked' => $query->where('track_inventory', false),
                            default     => $query,
                        };
                    }),

                // 🚀 FILTRO: Status da Cadeia de Custos
                Tables\Filters\SelectFilter::make('cost_chain_status')
                    ->label('Cadeia de Custos')
                    ->options(\App\Enums\CostChainStatus::class)
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (!$value) return $query;

                        $targetStatus = \App\Enums\CostChainStatus::tryFrom($value);
                        if (!$targetStatus) return $query;

                        // Avalia os IDs correspondentes em memória para precisão total da árvore (ignorando Combos)
                        $allIds = Product::where('type', '!=', ProductType::COMBO->value)->pluck('id');
                        $matchingIds = [];

                        foreach ($allIds as $pId) {
                            $p = Product::find($pId);
                            if ($p && $p->cost_chain_status === $targetStatus) {
                                $matchingIds[] = $pId;
                            }
                        }

                        return $query->whereIn('products.id', $matchingIds);
                    }),
            ])
            ->actions([])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    MergeProductsBulkAction::make(),

                    BulkAction::make('alterarCategoria')
                        ->label('Alterar Categoria')
                        ->icon('heroicon-o-tag')
                        ->form([
                            FormComponents\Select::make('category_id')
                                ->label('Nova Categoria')
                                ->options(fn () => Category::pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['category_id' => $data['category_id']])),

                    BulkAction::make('alterarStatus')
                        ->label('Alterar Status')
                        ->icon('heroicon-o-check-circle')
                        ->form([
                            FormComponents\Select::make('status')
                                ->label('Novo Status')
                                ->options(ProductStatus::class)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $successCount = 0;
                            $skippedCount = 0;
                            $availabilityService = app(AvailabilityService::class);

                            foreach ($records as $record) {
                                if ($data['status'] === 'available' && $record->track_inventory && (float) $record->current_stock <= 0) {
                                    $skippedCount++;
                                    continue;
                                }

                                try {
                                    $availabilityService->updateAvailability($record->id, $data['status']);
                                    $successCount++;
                                } catch (\Throwable $e) {
                                    $skippedCount++;
                                }
                            }

                            if ($skippedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Atenção ao Estoque')
                                    ->body("{$successCount} produtos atualizados. {$skippedCount} foram ignorados por estarem sem estoque ou com pendências.")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->success()
                                    ->title('Sucesso!')
                                    ->body("{$successCount} produtos atualizados com propagação em tempo real.")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
