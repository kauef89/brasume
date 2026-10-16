<?php

namespace App\Filament\Resources\LegacyNeighborhoods;

use App\Models\LegacyNeighborhood;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use App\Filament\Resources\LegacyNeighborhoods\Pages\ManageLegacyNeighborhoods;
use App\Filament\Resources\LegacyNeighborhoods\Schemas\LegacyNeighborhoodForm;
use App\Filament\Resources\LegacyNeighborhoods\Tables\LegacyNeighborhoodsTable;

class LegacyNeighborhoodResource extends Resource
{
    protected static ?string $model = LegacyNeighborhood::class;
    protected static string|\BackedEnum|null $navigationIcon = "heroicon-o-map";
    protected static string|\UnitEnum|null $navigationGroup = 'Sistema Legado';
    protected static ?string $modelLabel = "Bairro Antigo";
    protected static ?string $pluralModelLabel = "Bairros Antigos";

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = static::getModel()::whereNull("neighborhood_id")->count();
            return $count > 0 ? (string) $count : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return "warning";
    }

    public static function form(Schema $schema): Schema
    {
        return LegacyNeighborhoodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LegacyNeighborhoodsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            "index" => ManageLegacyNeighborhoods::route("/"),
        ];
    }
}
