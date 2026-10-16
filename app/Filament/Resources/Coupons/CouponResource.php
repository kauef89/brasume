<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Resources\Coupons\Pages;
use App\Filament\Resources\Coupons\Schemas\CouponForm;
use App\Filament\Resources\Coupons\Tables\CouponTable;
use App\Filament\Resources\Coupons\RelationManagers\OrdersRelationManager;
use App\Models\Coupon;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';
    protected static string|\UnitEnum|null $navigationGroup = 'CRM & Marketing';
    protected static ?string $modelLabel = 'Cupom de Desconto';
    protected static ?string $pluralModelLabel = 'Cupons e Promoções';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CouponForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CouponTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // O RelationManager que mostra as métricas e quem usou
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'view' => Pages\EditCoupon::route('/{record}'), // O View aponta pro Edit por conveniência, ou crie a ViewPage se preferir
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
