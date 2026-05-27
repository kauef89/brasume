<?php

namespace App\Filament\Resources\Kitchens\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;

class KitchenTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Cozinha')->searchable(),
                Tables\Columns\TextColumn::make('printer.name')->label('Impressora vinculada'),
                Tables\Columns\ToggleColumn::make('is_active')->label('Ativa'),
            ])
            ->recordActions([
                Actions\EditAction::make()->iconButton()->tooltip('Editar')
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])
            ]);
    }
}