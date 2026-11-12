<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\ModifierType;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductModifiersRelationManager extends RelationManager
{
    protected static string $relationship = 'modifiers';
    protected static ?string $title = 'Modificadores';
    protected static string|\BackedEnum|null $icon = 'heroicon-o-queue-list';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Grupo')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                TextColumn::make('portion_distribution_mode')
                    ->label('Modo de Rateio')
                    ->badge()
                    ->state(fn ($record) => ($record->portion_distribution_mode ?? 'additive') === 'proportional_split' ? 'Rateio Proporcional' : 'Aditivo')
                    ->color(fn (string $state) => $state === 'Rateio Proporcional' ? 'info' : 'gray'),

                TextColumn::make('channels')
                    ->label('Canais')
                    ->badge()
                    ->state(function ($record) {
                        $c = [];
                        if ($record->show_on_pos) $c[] = 'PDV';
                        if ($record->show_on_customer_app) $c[] = 'App';
                        return empty($c) ? 'Oculto' : implode(' / ', $c);
                    })
                    ->color(fn ($state) => $state === 'PDV / App' ? 'success' : ($state === 'Oculto' ? 'danger' : 'warning')),

                TextColumn::make('pivot.excluded_options')
                    ->label('Opções Ocultadas Neste Produto')
                    ->state(function ($record) {
                        $excluded = json_decode($record->pivot->excluded_options ?? '[]', true) ?? [];
                        if (empty($excluded)) return 'Todas Disponíveis';
                        return count($excluded) . ' opção(ões) oculta(s)';
                    })
                    ->badge()
                    ->color(fn ($state) => $state === 'Todas Disponíveis' ? 'success' : 'warning'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Vincular Novo Grupo')
                    ->preloadRecordSelect(),
            ])
            ->actions([
                Action::make('toggle_options')
                    ->label('Ocultar Opções')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->modalHeading('Ocultar Opções Neste Produto')
                    ->modalDescription('Desmarque as opções que NÃO devem aparecer quando o cliente for comprar este produto específico.')
                    ->form(function ($record) {
                        $options = $record->options()->pluck('name', 'id')->toArray();

                        return [
                            CheckboxList::make('active_options')
                                ->label('Opções Disponíveis')
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
                        $all = $record->options()->pluck('id')->toArray();
                        $excluded = array_values(array_diff($all, $active));

                        $livewire->getOwnerRecord()->modifiers()->updateExistingPivot($record->id, [
                            'excluded_options' => json_encode($excluded),
                        ]);
                    }),

                DetachAction::make()->label('Desvincular Grupo'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()->label('Desvincular Selecionados'),
                ]),
            ]);
    }
}