<?php

namespace App\Filament\Resources\CompanyAssets;

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\CompanyAssets\Pages;
use App\Filament\Resources\CompanyAssets\Schemas\CompanyAssetForm;
use App\Filament\Resources\CompanyAssets\Tables\CompanyAssetsTable;
use App\Models\CompanyAsset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CompanyAssetResource extends Resource
{
    use HasGlobalSearch;

    protected static ?string $model = CompanyAsset::class;
    protected static ?string $slug = 'patrimonio';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string | \UnitEnum | null $navigationGroup = 'Estoque & Compras';
    protected static ?string $modelLabel = 'Ativo Imobilizado';
    protected static ?string $pluralModelLabel = 'Patrimônio & Imobilizado';
    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return CompanyAssetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompanyAssetsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCompanyAssets::route('/'),
            'create' => Pages\CreateCompanyAsset::route('/create'),
            'view'   => Pages\ViewCompanyAsset::route('/{record}'),
            'edit'   => Pages\EditCompanyAsset::route('/{record}/edit'),
        ];
    }
}
