<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

// 🚀 IMPORTAÇÕES CORRETAS PARA FILAMENT V4 (Arquitetura Unificada)
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\BulkActionGroup; // Nota: BulkActionGroup geralmente se mantém no Tables, mas as ações filhas não.
use Filament\Tables\Columns\TextColumn;

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
                    ->label('Nome Interno')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Tipo de Modificador')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ingredient' => 'info',
                        'add_on' => 'success',
                        'removal' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('max_selections')
                    ->label('Máx. Seleções'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->headerActions([
                // 🚀 USANDO A AÇÃO NATIVA DO V4
                AttachAction::make()
                    ->label('Vincular Grupo')
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                    ]),
            ])
            ->actions([
                DetachAction::make()->label('Desvincular'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
