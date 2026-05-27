<?php

namespace App\Filament\Resources\Printers\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;

class PrinterTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('ip_address')->label('IP')->searchable(),
                Tables\Columns\TextColumn::make('port')->label('Porta'),
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