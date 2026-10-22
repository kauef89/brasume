<?php

namespace App\Filament\Resources\Products\Tables;


use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use App\Filament\Resources\Products\Actions\MergeProductsBulkAction;
use App\Models\Category;
use App\Models\Product;
use App\Filament\Resources\Products\ProductResource;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Components as FormComponents;
use App\Services\Catalog\ProductCostingService;
use App\Services\Catalog\AvailabilityService;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

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
                    ->sortable()
                    ->grow(true)
                    ->wrap(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match (strtolower($state)) {
                        ProductType::DISH->value => 'Prato',
                        ProductType::COMBO->value => 'Combo',
                        ProductType::RESALE->value => 'Revenda',
                        ProductType::INGREDIENT->value => 'Insumo',
                        ProductType::MANUFACTURED->value => 'Preparado',
                        default        => ucfirst($state),
                    })
                    ->color('gray')
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
                        if (in_array($record->type, [ProductType::INGREDIENT->value, ProductType::CONSUMABLE->value, ProductType::PACKAGING->value, ProductType::MANUFACTURED->value])) return null;

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
                    })
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
                        // 1. Barreira estrita: Disponível exige saldo físico positivo
                        if ($state === 'available' && $record->track_inventory && (float) $record->current_stock <= 0) {
                            Notification::make()
                                ->warning()
                                ->title('Estoque Zerado')
                                ->body("Você não pode colocar '{$record->name}' como disponível sem dar entrada no saldo.")
                                ->send();

                            $livewire->dispatch('$refresh');
                            return;
                        }

                        // 2. Dispara a máquina de estados completa do AvailabilityService
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

                Tables\Columns\TextColumn::make('connection_status')
                    ->label('Cadeia de custos')
                    ->state(function (Product $record) {
                        if ($record->type === ProductType::DISH->value) {
                            return $record->technicalSheets()->exists() ? 'Conectado' : 'Desconectado';
                        }

                        if ($record->type === ProductType::RESALE->value) {
                            return $record->costHistories()->exists() ? 'Conectado' : 'Desconectado';
                        }

                        return 'Conectado';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Conectado'    => 'gray',
                        'Desconectado' => 'danger',
                        default        => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Conectado'    => 'heroicon-m-link',
                        'Desconectado' => 'heroicon-m-link-slash',
                        default        => 'heroicon-m-link',
                    })
                    ->grow(false),
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
                    ->options(ProductStatus::class),
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
                        ->action(fn(Collection $records, array $data) => $records->each->update(['category_id' => $data['category_id']])),

                    Actions\BulkAction::make('alterarStatus')
                        ->label('Alterar Status')
                        ->icon('heroicon-o-check-circle')
                        ->form([
                            FormComponents\Select::make('status')
                                ->label('Novo Status')
                                ->options(ProductStatus::class)
                                ->required(),
                        ])
                        ->action(function(Collection $records, array $data) {
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
