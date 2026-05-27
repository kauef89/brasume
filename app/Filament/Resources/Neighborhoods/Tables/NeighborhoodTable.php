<?php
namespace App\Filament\Resources\Neighborhoods\Tables;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;

class NeighborhoodTable {
    public static function configure(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nome do Bairro')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('delivery_fee')->label('Taxa de Entrega')->money('BRL')->sortable(),
            Tables\Columns\TextColumn::make('free_shipping_over')->label('Frete Grátis Acima de')->money('BRL')->sortable(),
        ])->defaultSort('name')
        ->recordActions([Actions\EditAction::make()->iconButton()])
        ->toolbarActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }
}