<?php

namespace App\Filament\Resources\ProductModifiers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\CheckboxList;

// 🚀 Ações nativas unificadas do Filament
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\BulkActionGroup;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';
    protected static ?string $title = 'Produtos Vinculados';
    protected static ?string $modelLabel = 'Produto';
    protected static ?string $pluralModelLabel = 'Produtos';
    protected static string|\BackedEnum|null $icon = 'heroicon-o-shopping-bag';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Produto')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Categoria')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Sem Categoria'),

                TextColumn::make('type')
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

                TextColumn::make('dine_in_price')
                    ->label('Preço Salão')
                    ->money('BRL')
                    ->sortable(),

                // 🚀 Exibe se este produto específico tem opções deste grupo desativadas
                TextColumn::make('pivot.excluded_options')
                    ->label('Exceções Locais')
                    ->state(function ($record) {
                        $excluded = json_decode($record->pivot->excluded_options ?? '[]', true) ?? [];
                        if (empty($excluded)) return 'Todas Disponíveis';
                        return count($excluded) . ' opção(ões) oculta(s)';
                    })
                    ->badge()
                    ->color(fn ($state) => $state === 'Todas Disponíveis' ? 'success' : 'warning'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available' => 'Disponível',
                        'out_of_stock' => 'Esgotado',
                        'archived' => 'Arquivado',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'out_of_stock' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Filtrar por Categoria')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                // 🚀 Vínculo individual ou múltiplo com inicialização limpa do JSON da pivô
                AttachAction::make()
                    ->label('Vincular Produtos')
                    ->preloadRecordSelect()
                    ->multiple()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['excluded_options'] = json_encode([]);
                        return $data;
                    }),
            ])
            ->actions([
                // 🚀 Permite definir exceções de itens deste grupo para o produto selecionado
                Action::make('toggle_exceptions')
                    ->label('Exceções')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->modalHeading(fn ($record) => "Opções Ocultadas em: {$record->name}")
                    ->modalDescription('Desmarque as opções deste grupo que NÃO devem aparecer neste produto.')
                    ->form(function ($record, $livewire) {
                        $modifierGroup = $livewire->getOwnerRecord();
                        $options = $modifierGroup->options()->pluck('name', 'id')->toArray();

                        return [
                            CheckboxList::make('active_options')
                                ->label('Opções Ativas')
                                ->options($options)
                                ->default(function () use ($record, $options) {
                                    $excluded = json_decode($record->pivot->excluded_options ?? '[]', true) ?? [];
                                    return array_diff(array_keys($options), $excluded);
                                })
                                ->columns(2),
                        ];
                    })
                    ->action(function ($record, array $data, $livewire) {
                        $active = $data['active_options'] ?? [];
                        $modifierGroup = $livewire->getOwnerRecord();
                        $all = $modifierGroup->options()->pluck('id')->toArray();

                        $excluded = array_values(array_diff($all, $active));

                        $modifierGroup->products()->updateExistingPivot($record->id, [
                            'excluded_options' => json_encode($excluded),
                        ]);
                    }),

                DetachAction::make()
                    ->label('Desvincular'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Desvincular Selecionados'),
                ]),
            ]);
    }
}
