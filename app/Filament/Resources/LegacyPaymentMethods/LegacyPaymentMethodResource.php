<?php
namespace App\Filament\Resources\LegacyPaymentMethods;
use App\Models\LegacyPaymentMethod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Resources\ResourceConfiguration;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\LegacyPaymentMethods\Schemas\LegacyPaymentMethodForm;
use App\Filament\Resources\LegacyPaymentMethods\Tables\LegacyPaymentMethodsTable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
/**
 * @extends Resource<Model,ResourceConfiguration>
 */
class LegacyPaymentMethodResource extends Resource
{
    protected static ?string $model = LegacyPaymentMethod::class;
    protected static string|\BackedEnum|null $navigationIcon = "heroicon-o-credit-card";
    protected static string|\UnitEnum|null $navigationGroup = 'Sistema Legado';
    protected static ?string $navigationLabel = "Formas de Pagto";
    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        try {
            $count = static::getModel()
                ::whereNull("current_payment_method_id")
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
        return LegacyPaymentMethodForm::build($schema);
    }
    public static function table(Table $table): Table
    {
        return LegacyPaymentMethodsTable::build($table);
    }
    public static function getPages(): array
    {
        return [
            "index" => Pages\ManageLegacyPaymentMethods::route("/"),
        ];
    }
}
