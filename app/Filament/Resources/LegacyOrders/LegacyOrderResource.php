<?php
namespace App\Filament\Resources\LegacyOrders;
use App\Models\LegacyOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Resources\ResourceConfiguration;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\LegacyOrders\Infolists\LegacyOrderInfolist;
use App\Filament\Resources\LegacyOrders\Tables\LegacyOrdersTable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
/**
 * @extends Resource<Model,ResourceConfiguration>
 */
class LegacyOrderResource extends Resource
{
    protected static ?string $model = LegacyOrder::class;
    protected static string|\BackedEnum|null $navigationIcon = "heroicon-o-receipt-refund";
    protected static string|\UnitEnum|null $navigationGroup = "Legado Fiscal";
    protected static ?string $navigationLabel = "Pedidos Antigos";
    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return LegacyOrderInfolist::build($schema);
    }
    public static function table(Table $table): Table
    {
        return LegacyOrdersTable::build($table);
    }
    public static function getPages(): array
    {
        return [
            "index" => Pages\ManageLegacyOrders::route("/"),
        ];
    }
    public static function canCreate(): bool
    {
        return false;
    } // Impede criação manual de histórico
}
