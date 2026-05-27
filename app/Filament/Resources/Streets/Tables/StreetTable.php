<?php
namespace App\Filament\Resources\Streets\Tables;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;

class StreetTable {
    public static function configure(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nome da Rua')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('zip_code')->label('CEP Geral')->searchable(),
            Tables\Columns\TextColumn::make('segments_count')->counts('segments')->label('Qtd. de Faixas')->badge(),
        ])->defaultSort('name')
        ->recordActions([Actions\EditAction::make()->iconButton()])
        ->toolbarActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }
}