<?php

namespace App\Filament\Resources\OrderRatings;

use App\Filament\Resources\OrderRatings\Pages;
use App\Filament\Resources\OrderRatings\Tables\OrderRatingsTable;
use App\Models\OrderRating;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class OrderRatingResource extends Resource
{
    protected static ?string $model = OrderRating::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';
    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';
    protected static ?string $slug = 'avaliacoes-nps';
    protected static ?string $modelLabel = 'Avaliação de Pedido';
    protected static ?string $pluralModelLabel = 'Avaliações (NPS)';
    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function table(Table $table): Table
    {
        return OrderRatingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderRatings::route('/'),
        ];
    }
}