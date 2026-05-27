<?php
namespace App\Filament\Resources\Coupons\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions;

class CouponTable {
    public static function configure(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('Código')->searchable()->sortable()->badge()->color('info'),
            Tables\Columns\TextColumn::make('type')->label('Tipo')->formatStateUsing(fn (string $state): string => match ($state) { 'fixed' => 'Fixo', 'percent' => 'Porcentagem', default => $state }),
            Tables\Columns\TextColumn::make('value')->label('Valor')->sortable(),
            Tables\Columns\TextColumn::make('expires_at')->label('Vence em')->dateTime('d/m/Y H:i')->sortable(),
            Tables\Columns\ToggleColumn::make('is_active')->label('Ativo'),
        ])->defaultSort('created_at', 'desc')
        ->recordActions([Actions\EditAction::make()->iconButton()])
        ->toolbarActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }
}