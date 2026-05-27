<?php
namespace App\Filament\Resources\ProductModifiers\Tables;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;

class ProductModifierTable {
    public static function configure(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
            Tables\Columns\TextColumn::make('type')->label('Tipo')->badge(),
            Tables\Columns\ToggleColumn::make('is_active')->label('Ativo'),
        ])->recordActions([Actions\EditAction::make()->iconButton()])
        ->toolbarActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }
}