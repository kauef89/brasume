<?php
namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\Coupons\Pages;
use App\Filament\Resources\Coupons\Schemas\CouponForm;
use App\Filament\Resources\Coupons\Tables\CouponTable;
use App\Models\Coupon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CouponResource extends Resource {
    protected static ?string $model = Coupon::class;
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-ticket';
    protected static string | \UnitEnum | null $navigationGroup = 'CRM';
    protected static ?string $modelLabel = 'Cupom';
    protected static ?string $pluralModelLabel = 'Cupons';

    public static function form(Schema $schema): Schema { return CouponForm::configure($schema); }
    public static function table(Table $table): Table { return CouponTable::configure($table); }
    public static function getPages(): array {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}