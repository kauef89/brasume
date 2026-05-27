<?php
namespace App\Filament\Resources\Offers\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;

class OfferTable {
    public static function configure(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->label('Título')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('promo_price')->label('Preço Promo')->money('BRL')->sortable(),
            Tables\Columns\TextColumn::make('triggerProduct.name')->label('Produto Gatilho')->searchable(),
            Tables\Columns\ToggleColumn::make('is_active')->label('Ativa'),
        ])->defaultSort('title')
        ->recordActions([Actions\EditAction::make()->iconButton()])
        ->toolbarActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }
}