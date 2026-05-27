<?php
namespace App\Filament\Resources\Categories\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\EditAction; // <-- Correção aqui
use Filament\Actions\BulkActionGroup; // <-- Correção aqui
use Filament\Actions\DeleteBulkAction; // <-- Correção aqui
use Filament\Tables\Filters\SelectFilter;

class CategoryTable {
    public static function configure(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('sort_order')
                ->label('Ordem')
                ->sortable(),
                
            Tables\Columns\TextColumn::make('name')
                ->label('Nome')
                ->searchable(),
                
            Tables\Columns\TextColumn::make('type')
                ->label('Classificação')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'external' => 'success',
                    'internal' => 'warning',
                    'both'     => 'info',
                    'supply'   => 'gray',
                    default    => 'primary',
                })
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'internal' => 'Insumos',
                    'supply'   => 'Uso e Consumo',
                    'external' => 'Produto Final',
                    'both'     => 'Ambos',
                    default    => ucfirst($state),
                })
                ->sortable()
                ->searchable(),
                
            Tables\Columns\ToggleColumn::make('is_active')
                ->label('Ativa'),
        ])
        ->defaultSort('sort_order')
        ->filters([
            SelectFilter::make('type')
                ->label('Filtrar por Classificação')
                ->options([
                    'internal' => 'Insumos',
                    'supply'   => 'Uso e Consumo',
                    'external' => 'Produto Final',
                    'both'     => 'Ambos',
                ])
        ])
        // Correção das chamadas das ações:
        ->recordActions([EditAction::make()->iconButton()])
        ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}