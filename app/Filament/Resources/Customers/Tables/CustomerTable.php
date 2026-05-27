<?php
namespace App\Filament\Resources\Customers\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;

class CustomerTable {
    public static function configure(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('cpf')->label('CPF')->searchable(),
            Tables\Columns\TextColumn::make('phone')->label('Telefone')->searchable(),
            Tables\Columns\TextColumn::make('addresses_count')->counts('addresses')->label('Endereços')->badge(),
        ])->defaultSort('name')
        ->recordActions([Actions\EditAction::make()->iconButton()])
        ->toolbarActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }
}