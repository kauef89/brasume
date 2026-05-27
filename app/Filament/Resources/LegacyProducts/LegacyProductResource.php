<?php

namespace App\Filament\Resources\LegacyProducts;

use App\Models\LegacyProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Resources\ResourceConfiguration;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\LegacyProducts\Schemas\LegacyProductForm;
use App\Filament\Resources\LegacyProducts\Tables\LegacyProductsTable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
/**
 * @extends Resource<Model,ResourceConfiguration>
 */
class LegacyProductResource extends Resource
{
    protected static ?string $model = LegacyProduct::class;

    // 🚀 A TIPAGEM ESTRITA DO FILAMENT 4/5
    protected static string|\BackedEnum|null $navigationIcon = "heroicon-o-archive-box";
    protected static string|\UnitEnum|null $navigationGroup = "Legado Fiscal";
    protected static ?string $navigationLabel = "Produtos Antigos";
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = "Produto Legado";

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = static::getModel()
                ::whereNull("current_product_id")
                ->where("is_ignored", false)
                ->count();

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
        return LegacyProductForm::build($schema);
    }

    public static function table(Table $table): Table
    {
        return LegacyProductsTable::build($table);
    }

    public static function getPages(): array
    {
        return [
            "index" => Pages\ManageLegacyProducts::route("/"),
        ];
    }
}
