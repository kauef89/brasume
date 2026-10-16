<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\CheckboxList;

// 🚀 IMPORTAÇÕES CORRETAS PARA FILAMENT V4 (Arquitetura Unificada)
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\BulkActionGroup;

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
                    ->color(fn (string $state): string => match ($state) {
                        'ingredient' => 'info',
                        'add_on' => 'success',
                        'removal' => 'danger',
                        default => 'gray',
                    }),

                // 🚀 INDICADOR DE EXCEÇÕES LOCAIS
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
                // 🚀 USANDO A AÇÃO NATIVA DO V4
                AttachAction::make()
                    ->label('Vincular Novo Grupo')
                    ->preloadRecordSelect(),
            ])
            ->actions([
                // 🚀 A MÁGICA LOCAL: Ligar/Desligar itens apenas para este produto
                Action::make('toggle_options')
                    ->label('Ocultar Opções')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->modalHeading('Ocultar Opções Neste Produto')
                    ->modalDescription('Desmarque as opções que NÃO devem aparecer quando o cliente for comprar este produto específico.')
                    ->form(function ($record) {
                        // Busca todas as opções cadastradas neste grupo
                        $options = $record->options()->pluck('name', 'id')->toArray();

                        return [
                            CheckboxList::make('active_options')
                                ->label('Opções Disponíveis')
                                ->options($options)
                                ->default(function () use ($record, $options) {
                                    // Puxa as que estão excluídas na Pivot
                                    $excluded = json_decode($record->pivot->excluded_options ?? '[]', true) ?? [];
                                    // Marca as que NÃO estão excluídas
                                    return array_diff(array_keys($options), $excluded);
                                })
                                ->columns(2)
                        ];
                    })
                    ->action(function ($record, array $data, $livewire) {
                        $active = $data['active_options'] ?? [];
                        $all = $record->options()->pluck('id')->toArray();

                        // O que sobrou (não está ativo) vai para o array de excluídos
                        $excluded = array_values(array_diff($all, $active));

                        // Salva a exceção na Pivot do Produto!
                        $livewire->getOwnerRecord()->modifiers()->updateExistingPivot($record->id, [
                            'excluded_options' => json_encode($excluded)
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
